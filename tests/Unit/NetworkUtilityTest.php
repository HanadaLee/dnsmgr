<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\utils\CheckUtils;
use app\utils\DnsQueryUtils;
use PHPUnit\Framework\TestCase;

final class NetworkUtilityTest extends TestCase
{
    public function testUnsupportedDnsRecordTypesAreRejectedWithoutNetworkAccess(): void
    {
        self::assertFalse(DnsQueryUtils::get_dns_records('example.com', 'INVALID'));
        self::assertFalse(DnsQueryUtils::query_dns_doh('example.com', 'INVALID'));
    }

    public function testHttpMonitorRejectsInvalidUrlWithoutNetworkAccess(): void
    {
        self::assertSame(
            ['status' => false, 'errmsg' => 'Invalid URL', 'usetime' => 0],
            CheckUtils::curl('not-a-url', 1)
        );
    }

    public function testPingMonitorRejectsInvalidTargetWithoutExecutingPing(): void
    {
        self::assertSame(
            ['status' => false, 'errmsg' => 'Invalid IP address', 'usetime' => 0],
            CheckUtils::ping('invalid target', null)
        );
    }
}
