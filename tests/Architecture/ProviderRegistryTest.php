<?php

declare(strict_types=1);

namespace Tests\Architecture;

use app\lib\CertHelper;
use app\lib\CertInterface;
use app\lib\DeployHelper;
use app\lib\DeployInterface;
use app\lib\DnsHelper;
use app\lib\DnsInterface;
use PHPUnit\Framework\TestCase;

final class ProviderRegistryTest extends TestCase
{
    public function testEveryDnsProviderIsRegisteredAndImplementsContract(): void
    {
        self::assertSame($this->phpModuleNames('dns'), $this->sortedKeys(DnsHelper::getList()));

        foreach (DnsHelper::getList() as $type => $definition) {
            $class = 'app\\lib\\dns\\' . $type;
            self::assertTrue(class_exists($class), sprintf('DNS 适配器 %s 无法加载', $type));
            self::assertTrue(is_subclass_of($class, DnsInterface::class), sprintf('DNS 适配器 %s 未实现 DnsInterface', $type));
            self::assertNotSame('', trim((string)($definition['name'] ?? '')), sprintf('DNS 适配器 %s 缺少名称', $type));
            self::assertIsArray($definition['config'] ?? null, sprintf('DNS 适配器 %s 配置结构无效', $type));
            $this->assertFieldsAreValid($definition['config'], true, 'DNS/' . $type);
        }
    }

    public function testEveryCertificateAuthorityIsRegisteredAndImplementsContract(): void
    {
        self::assertSame([], array_diff($this->sortedKeys(CertHelper::getList()), $this->phpModuleNames('cert')));

        foreach (CertHelper::getList() as $type => $definition) {
            $class = 'app\\lib\\cert\\' . $type;
            self::assertTrue(class_exists($class), sprintf('CA 适配器 %s 无法加载', $type));
            self::assertTrue(is_subclass_of($class, CertInterface::class), sprintf('CA 适配器 %s 未实现 CertInterface', $type));
            self::assertNotSame('', trim((string)($definition['name'] ?? '')), sprintf('CA 适配器 %s 缺少名称', $type));
            self::assertContains($definition['class'] ?? null, array_keys(CertHelper::$class_config));
            self::assertIsArray($definition['inputs'] ?? null);
            $this->assertFieldsAreValid($definition['inputs'], true, 'CA/' . $type);
            $this->assertInputHydration(CertHelper::class, $type, $definition['inputs']);
        }
    }

    public function testEveryDeploymentProviderIsRegisteredAndImplementsContract(): void
    {
        self::assertSame($this->phpModuleNames('deploy'), $this->sortedKeys(DeployHelper::getList()));

        foreach (DeployHelper::getList() as $type => $definition) {
            $class = 'app\\lib\\deploy\\' . $type;
            self::assertTrue(class_exists($class), sprintf('部署适配器 %s 无法加载', $type));
            self::assertTrue(is_subclass_of($class, DeployInterface::class), sprintf('部署适配器 %s 未实现 DeployInterface', $type));
            self::assertNotSame('', trim((string)($definition['name'] ?? '')), sprintf('部署适配器 %s 缺少名称', $type));
            self::assertContains($definition['class'] ?? null, array_keys(DeployHelper::$class_config));
            self::assertIsArray($definition['inputs'] ?? null);
            $this->assertFieldsAreValid($definition['inputs'], true, 'Deploy/' . $type);
            $this->assertInputHydration(DeployHelper::class, $type, $definition['inputs']);
        }
    }

    public function testUnknownProviderTypesAreRejected(): void
    {
        self::assertFalse(DnsHelper::getModel2(['type' => 'missing', 'config' => '{}', 'name' => 'example.com', 'thirdid' => '1']));
        self::assertFalse(CertHelper::getModel2('missing', []));
        self::assertFalse(DeployHelper::getModel2('missing', []));
    }

    private function phpModuleNames(string $directory): array
    {
        $files = glob(dirname(__DIR__, 2) . '/app/lib/' . $directory . '/*.php') ?: [];
        $names = array_map(static fn(string $file): string => basename($file, '.php'), $files);
        sort($names);
        return $names;
    }

    private function sortedKeys(array $items): array
    {
        $keys = array_keys($items);
        sort($keys);
        return $keys;
    }

    private function assertFieldsAreValid(array $fields, bool $associative, string $scope): void
    {
        $seen = [];
        foreach ($fields as $key => $field) {
            self::assertIsArray($field, $scope . ' 字段配置必须是数组');
            $name = $associative ? (string)$key : (string)($field['name'] ?? '');
            self::assertNotSame('', $name, $scope . ' 存在未命名字段');
            self::assertNotContains($name, $seen, $scope . ' 存在重复字段 ' . $name);
            $seen[] = $name;
            self::assertNotSame('', trim((string)($field['name'] ?? '')), $scope . '/' . $name . ' 缺少显示名称');
            self::assertContains($field['type'] ?? null, ['input', 'radio', 'select', 'textarea'], $scope . '/' . $name . ' 字段类型无效');
            if (in_array($field['type'], ['radio', 'select'], true)) {
                self::assertNotEmpty($field['options'] ?? [], $scope . '/' . $name . ' 缺少选项');
            }
        }
    }

    private function assertInputHydration(string $helper, string $type, array $inputs): void
    {
        if ($inputs === []) {
            return;
        }
        $name = (string)array_key_first($inputs);
        $value = 'configured-' . $type;
        $hydrated = $helper::getInputs($type, json_encode([$name => $value]));
        self::assertSame($value, $hydrated[$name]['value'] ?? null, sprintf('%s::getInputs 未回填 %s', $helper, $name));
    }
}
