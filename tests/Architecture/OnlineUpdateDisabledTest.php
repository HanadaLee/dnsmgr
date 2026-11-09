<?php

declare(strict_types=1);

namespace Tests\Architecture;

use app\controller\System;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OnlineUpdateDisabledTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    #[DataProvider('onlineUpdateActions')]
    public function testOnlineUpdateHasNoRouteOrControllerAction(string $action): void
    {
        $routes = (string)file_get_contents(self::ROOT . '/route/app.php');

        self::assertStringNotContainsString('/system/' . $action, $routes);
        self::assertFalse(method_exists(System::class, $action));
    }

    public static function onlineUpdateActions(): array
    {
        return [['update'], ['checkupdate'], ['doupdate']];
    }

    public function testOnlineUpdateHasNoMenuTemplateOrPackageInstaller(): void
    {
        $layout = (string)file_get_contents(self::ROOT . '/app/view/common/layout.html');

        self::assertStringNotContainsString('/system/update', $layout);
        self::assertFileDoesNotExist(self::ROOT . '/app/view/system/update.html');
        self::assertFileDoesNotExist(self::ROOT . '/app/service/UpdateService.php');
        self::assertFalse(class_exists('app\\service\\UpdateService'));
    }

    public function testDashboardDoesNotLoadRemoteUpdateScripts(): void
    {
        $controller = (string)file_get_contents(self::ROOT . '/app/controller/Index.php');
        $template = (string)file_get_contents(self::ROOT . '/app/view/index/index.html');

        self::assertStringNotContainsString('auth.cccyun.cc/app/dnsmgr.php', $controller);
        self::assertStringNotContainsString('{$checkupdate}', $template);
        self::assertStringNotContainsString('jsonpCallback', $template);
        self::assertStringContainsString("config('app.version')", $template);
    }
}
