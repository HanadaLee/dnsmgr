<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use think\App;
use think\facade\Db;

final class DatabaseSchemaTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private static bool $enabled = false;

    public static function setUpBeforeClass(): void
    {
        self::$enabled = getenv('DNSMGR_TEST_DATABASE') === '1';
        if (!self::$enabled) {
            return;
        }

        $host = getenv('PHP_DATABASE_HOSTNAME') ?: '127.0.0.1';
        $port = getenv('PHP_DATABASE_HOSTPORT') ?: '3306';
        $database = getenv('PHP_DATABASE_DATABASE') ?: 'dnsmgr_test';
        $username = getenv('PHP_DATABASE_USERNAME') ?: 'root';
        $password = getenv('PHP_DATABASE_PASSWORD') ?: 'test';
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec("SET sql_mode = ''");
        foreach (explode(';', (string)file_get_contents(self::ROOT . '/app/sql/install.sql')) as $statement) {
            if (trim($statement) !== '') {
                $pdo->exec($statement);
            }
        }

        (new App(realpath(self::ROOT)))->initialize();
    }

    protected function setUp(): void
    {
        if (!self::$enabled) {
            self::markTestSkipped('设置 DNSMGR_TEST_DATABASE=1 后运行 MySQL 集成测试');
        }
    }

    public function testInstallSchemaCreatesEveryFeatureTable(): void
    {
        $expected = [
            'account', 'axisnow_rule_automation', 'axisnow_rule_automation_log',
            'cert_account', 'cert_cname', 'cert_deploy', 'cert_domain', 'cert_order',
            'config', 'dmlog', 'dmtask', 'domain', 'domain_alias', 'domain_category',
            'log', 'optimizeip', 'permission', 'sctask', 'user',
        ];
        $rows = Db::query("SHOW TABLES LIKE 'dnsmgr_%'");
        $actual = [];
        foreach ($rows as $row) {
            $name = (string)reset($row);
            $actual[] = substr($name, strlen('dnsmgr_'));
        }
        sort($actual);

        self::assertSame($expected, $actual);
    }

    public function testCoreConfigurationCanBeReadAndUpdatedThroughThinkOrm(): void
    {
        self::assertSame('1050', Db::name('config')->where('key', 'version')->value('value'));
        self::assertTrue(config_set('test_key', 'test_value'));
        self::assertSame('test_value', config_get('test_key', null, true));
        self::assertTrue(checkTableExists('config'));
        self::assertFalse(checkTableExists('missing_table'));
    }

    public function testFeatureSchemasContainRequiredColumns(): void
    {
        $requirements = [
            'account' => ['id', 'type', 'name', 'config'],
            'domain' => ['id', 'aid', 'name', 'thirdid'],
            'dmtask' => ['id', 'did', 'recordid', 'type', 'active'],
            'sctask' => ['id', 'did', 'recordid', 'switchtype', 'nexttime'],
            'optimizeip' => ['id', 'did', 'recordnum', 'active'],
            'cert_order' => ['id', 'aid', 'status', 'fullchain', 'privatekey'],
            'cert_deploy' => ['id', 'aid', 'oid', 'status'],
            'axisnow_rule_automation' => ['id', 'account_id', 'rule_uuid', 'primary_pool', 'failover_state'],
            'axisnow_rule_automation_log' => ['id', 'automation_id', 'action', 'status'],
        ];

        foreach ($requirements as $table => $columns) {
            $actual = array_column(Db::query('SHOW COLUMNS FROM `dnsmgr_' . $table . '`'), 'Field');
            foreach ($columns as $column) {
                self::assertContains($column, $actual, sprintf('dnsmgr_%s 缺少字段 %s', $table, $column));
            }
        }
    }
}
