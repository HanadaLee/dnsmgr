<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CoreUtilityTest extends TestCase
{
    public function testAuthcodeRoundTripAndWrongKeyRejection(): void
    {
        $encoded = authcode('payload-中文', 'ENCODE', 'secret-key');

        self::assertNotSame('payload-中文', $encoded);
        self::assertSame('payload-中文', authcode($encoded, 'DECODE', 'secret-key'));
        self::assertSame('', authcode($encoded, 'DECODE', 'wrong-key'));
    }

    public function testRandomProducesRequestedCharacterClasses(): void
    {
        self::assertMatchesRegularExpression('/^[0-9]{32}$/', random(32, 1));
        self::assertMatchesRegularExpression('/^[0-9A-Za-z]{32}$/', random(32));
    }

    public function testRealIpHonorsTrustedHeaderModes(): void
    {
        $original = $_SERVER;
        try {
            $_SERVER['REMOTE_ADDR'] = '192.168.1.2';
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1, 8.8.8.8';
            $_SERVER['HTTP_CF_CONNECTING_IP'] = '1.1.1.1';

            self::assertSame('8.8.8.8', real_ip(0));
            self::assertSame('1.1.1.1', real_ip(1));
            self::assertSame('192.168.1.2', real_ip(2));
        } finally {
            $_SERVER = $original;
        }
    }

    public function testTimeAndPasswordHelpers(): void
    {
        self::assertSame('59秒', convert_second(59));
        self::assertSame('1分钟1秒', convert_second(61));
        self::assertSame('1小时1分钟1秒', convert_second(3661));
        self::assertSame(getMd5Pwd('password', 'salt'), getMd5Pwd('password', 'salt'));
        self::assertNotSame(getMd5Pwd('password', 'salt'), getMd5Pwd('password', 'other'));
        self::assertGreaterThan(0, getMillisecond());
    }

    public function testInternationalDomainConversion(): void
    {
        self::assertSame('xn--fsqu00a.xn--0zwm56d', convertDomainToAscii('例子.测试'));
        self::assertSame('例子.测试', convertDomainToUtf8('xn--fsqu00a.xn--0zwm56d'));
        self::assertSame('example.com', convertDomainToAscii('example.com'));
    }

    public function testClearDirectoryRemovesNestedContentsButKeepsRoot(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dnsmgr-test-' . bin2hex(random_bytes(8));
        $nested = $directory . DIRECTORY_SEPARATOR . 'nested';
        mkdir($nested, 0777, true);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'root.txt', 'root');
        file_put_contents($nested . DIRECTORY_SEPARATOR . 'nested.txt', 'nested');

        try {
            self::assertTrue(clearDirectory($directory));
            self::assertSame(['.', '..'], scandir($directory));
        } finally {
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testClearDirectoryRejectsMissingPath(): void
    {
        self::assertFalse(clearDirectory(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dnsmgr-missing-' . bin2hex(random_bytes(8))));
    }
}
