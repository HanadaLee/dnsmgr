<?php

declare(strict_types=1);

namespace Tests\Architecture;

use app\BaseController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use think\App;

final class ApplicationStructureTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    public function testThinkPhpApplicationInitializes(): void
    {
        try {
            $app = new App(realpath(self::ROOT));
            $app->initialize();

            self::assertTrue($app->initialized());
            self::assertSame('Asia/Shanghai', $app->config->get('app.default_timezone'));
            self::assertSame('1053', $app->config->get('app.version'));
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    #[DataProvider('routeTargetProvider')]
    public function testEveryRouteTargetsAPublicControllerAction(string $target): void
    {
        [$controller, $action] = explode('/', $target, 2);
        $class = 'app\\controller\\' . ucfirst($controller);

        self::assertTrue(class_exists($class), sprintf('路由 %s 对应的控制器 %s 不存在', $target, $class));
        self::assertTrue(is_subclass_of($class, BaseController::class), sprintf('%s 未继承 BaseController', $class));
        self::assertTrue(method_exists($class, $action), sprintf('路由 %s 对应的方法不存在', $target));
        self::assertTrue((new ReflectionMethod($class, $action))->isPublic(), sprintf('路由 %s 对应的方法不是 public', $target));
    }

    public static function routeTargetProvider(): array
    {
        $source = (string)file_get_contents(self::ROOT . '/route/app.php');
        preg_match_all(
            "/Route::(?:any|get|post|put|patch|delete)\\(\\s*[^,]+,\\s*'([a-z0-9_]+\/[a-z0-9_]+)'/i",
            $source,
            $matches
        );

        $targets = array_values(array_unique($matches[1] ?? []));
        sort($targets);

        return array_combine($targets, array_map(static fn(string $target): array => [$target], $targets)) ?: [];
    }

    public function testConfiguredFrameworkClassesExist(): void
    {
        $console = require self::ROOT . '/config/console.php';
        $middleware = require self::ROOT . '/app/middleware.php';
        $services = require self::ROOT . '/app/service.php';
        $providers = require self::ROOT . '/app/provider.php';
        $classes = array_merge(
            array_values($console['commands']),
            array_values($middleware),
            array_values($services),
            array_values($providers)
        );

        foreach ($classes as $class) {
            self::assertTrue(class_exists($class), sprintf('配置引用的类 %s 不存在', $class));
        }
    }

    public function testEveryLiteralDatabaseTableExistsInSchema(): void
    {
        $usedTables = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/app'));
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $source = (string)file_get_contents($file->getPathname());
            preg_match_all("/Db::name\\(\\s*['\"]([a-z0-9_]+)['\"]\\s*\\)/i", $source, $matches);
            $usedTables = array_merge($usedTables, $matches[1] ?? []);
        }

        $schema = (string)file_get_contents(self::ROOT . '/app/sql/install.sql')
            . "\n"
            . (string)file_get_contents(self::ROOT . '/app/sql/update.sql');
        preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)? `dnsmgr_([^`]+)`/i', $schema, $matches);
        $definedTables = array_values(array_unique($matches[1] ?? []));
        $missing = array_values(array_diff(array_unique($usedTables), $definedTables));

        self::assertSame([], $missing, '代码引用了未在安装或升级 SQL 中定义的表：' . implode(', ', $missing));
    }

    public function testAllApplicationClassesCanBeAutoloaded(): void
    {
        $classes = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/app'));
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $classes = array_merge($classes, $this->declarations((string)file_get_contents($file->getPathname())));
        }

        self::assertNotEmpty($classes);
        foreach ($classes as [$type, $class]) {
            $loaded = match ($type) {
                T_INTERFACE => interface_exists($class),
                T_TRAIT => trait_exists($class),
                default => class_exists($class),
            };
            self::assertTrue($loaded, sprintf('%s 无法通过 Composer 自动加载', $class));
        }
    }

    private function declarations(string $source): array
    {
        $tokens = token_get_all($source, TOKEN_PARSE);
        $namespace = '';
        $declarations = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i])) {
                continue;
            }
            if ($tokens[$i][0] === T_NAMESPACE) {
                $namespace = '';
                for ($i++; $i < $count; $i++) {
                    if (is_string($tokens[$i]) && ($tokens[$i] === ';' || $tokens[$i] === '{')) {
                        break;
                    }
                    if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                        $namespace .= $tokens[$i][1];
                    }
                }
                continue;
            }
            if (!in_array($tokens[$i][0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                continue;
            }
            if ($tokens[$i][0] === T_CLASS && $i > 0 && is_array($tokens[$i - 1]) && $tokens[$i - 1][0] === T_NEW) {
                continue;
            }
            for ($j = $i + 1; $j < $count; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $declarations[] = [$tokens[$i][0], ltrim($namespace . '\\' . $tokens[$j][1], '\\')];
                    break;
                }
            }
        }
        return $declarations;
    }
}
