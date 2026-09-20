<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommonFunctionsTest extends TestCase
{
    #[DataProvider('domainProvider')]
    public function testCheckDomain(string $domain, bool $expected): void
    {
        self::assertSame($expected, checkDomain($domain));
    }

    public static function domainProvider(): array
    {
        return [
            'root domain' => ['example.com', true],
            'wildcard domain' => ['*.example.com', true],
            'underscore record' => ['_acme-challenge.example.com', true],
            'trailing dot' => ['example.com.', false],
            'leading dot' => ['.example.com', false],
            'invalid wildcard' => ['*example.com', false],
            'too short' => ['a.b', false],
        ];
    }

    #[DataProvider('dnsTypeProvider')]
    public function testGetDnsType(string $value, string $expected): void
    {
        self::assertSame($expected, getDnsType($value));
    }

    public static function dnsTypeProvider(): array
    {
        return [
            'IPv4' => ['192.0.2.1', 'A'],
            'IPv6' => ['2001:db8::1', 'AAAA'],
            'hostname' => ['target.example.com', 'CNAME'],
        ];
    }

    public function testGetSubstrHandlesBoundedAndOpenEndedValues(): void
    {
        self::assertSame('value', getSubstr('prefix[value]suffix', '[', ']'));
        self::assertSame('value', getSubstr('prefix:value', ':', ']'));
        self::assertSame('', getSubstr('prefix:value', '[', ']'));
    }

    public function testArraysAreEqualIgnoresOrder(): void
    {
        self::assertTrue(arrays_are_equal(['A', 'AAAA'], ['AAAA', 'A']));
        self::assertFalse(arrays_are_equal(['A'], ['AAAA']));
    }

    public function testNormalizeHttpHeadersConvertsScalarsAndFiltersInvalidValues(): void
    {
        self::assertSame([
            'X-String' => 'value',
            'X-Number' => '123',
            'X-List' => ['one', '2', ''],
        ], normalize_http_headers([
            'X-String' => 'value',
            'X-Number' => 123,
            'X-List' => ['one', 2, null, new \stdClass(), ''],
            'X-Null' => null,
            'X-Object' => new \stdClass(),
        ]));
    }

    public function testFindManagedDomainUsesLongestMatchingZone(): void
    {
        self::assertSame(
            'sub.example.com',
            findManagedDomain('host.sub.example.com', ['example.com', 'sub.example.com'])
        );
    }

    public function testFindManagedDomainPreservesExactAndParentOnlyMatches(): void
    {
        self::assertSame('sub.example.com', findManagedDomain('sub.example.com', ['example.com', 'sub.example.com']));
        self::assertSame('example.com', findManagedDomain('host.sub.example.com', ['example.com']));
    }

    public function testFindManagedDomainRequiresLabelBoundary(): void
    {
        self::assertNull(findManagedDomain('badexample.com', ['example.com']));
        self::assertNull(findManagedDomain('example.com.evil.test', ['example.com']));
    }
}
