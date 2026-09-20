<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\service\AxisNowAutomationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AxisNowAutomationServiceTest extends TestCase
{
    #[DataProvider('tideWindowProvider')]
    public function testTideWindow(string $start, string $end, string $current, bool $expected): void
    {
        self::assertSame($expected, AxisNowAutomationService::isTideWindow($start, $end, strtotime($current)));
    }

    public static function tideWindowProvider(): array
    {
        return [
            'inside normal window' => ['09:00', '18:00', '2026-09-10 12:00:00', true],
            'normal end boundary' => ['09:00', '18:00', '2026-09-10 18:00:00', false],
            'first half of overnight window' => ['22:00', '06:00', '2026-09-10 23:30:00', true],
            'second half of overnight window' => ['22:00', '06:00', '2026-09-11 05:30:00', true],
            'outside overnight window' => ['22:00', '06:00', '2026-09-10 12:00:00', false],
            'equal bounds disable window' => ['09:00', '09:00', '2026-09-10 12:00:00', false],
        ];
    }

    #[DataProvider('healthStateProvider')]
    public function testHealthState(array $rule, ?array $probeStatuses, string $expected): void
    {
        self::assertSame($expected, AxisNowAutomationService::healthState($rule, $probeStatuses));
    }

    public static function healthStateProvider(): array
    {
        $entry = static fn(string $address, ?string $status): array => [
            'address' => $address,
            'stability_info' => $status === null ? [] : ['status' => $status],
        ];

        return [
            'all candidates healthy' => [[
                'eips_count' => 2,
                'election_info' => ['list' => [$entry('192.0.2.1', 'available'), $entry('192.0.2.2', 'available')]],
            ], null, 'healthy'],
            'one candidate failed' => [[
                'eips_count' => 2,
                'election_info' => ['list' => [$entry('192.0.2.1', 'unavailable'), $entry('192.0.2.2', 'available')]],
            ], null, 'partial'],
            'all candidates failed' => [[
                'eips_count' => 2,
                'election_info' => ['list' => [$entry('192.0.2.1', 'unavailable'), $entry('192.0.2.2', 'unavailable')]],
            ], null, 'all_failed'],
            'missing status' => [[
                'eips_count' => 2,
                'election_info' => ['list' => [$entry('192.0.2.1', null), $entry('192.0.2.2', 'unavailable')]],
            ], null, 'no_data'],
            'explicit no data status' => [[
                'eips_count' => 2,
                'election_info' => ['list' => [$entry('192.0.2.1', 'no_data'), $entry('192.0.2.2', 'unavailable')]],
            ], null, 'no_data'],
            'incomplete candidate list' => [[
                'eips_count' => 3,
                'election_info' => ['list' => [$entry('192.0.2.1', 'unavailable'), $entry('192.0.2.2', 'unavailable')]],
            ], null, 'no_data'],
            'empty candidate list' => [[
                'eips_count' => 2,
                'election_info' => ['list' => []],
            ], null, 'no_data'],
            'unknown candidate count' => [[
                'eips_count' => 0,
                'election_info' => ['list' => [$entry('192.0.2.1', 'unavailable')]],
            ], null, 'no_data'],
            'probe API healthy' => [[
                'eips_count' => 2,
                'election_info' => null,
            ], [
                ['target' => '192.0.2.1', 'status' => 'available'],
                ['target' => '192.0.2.2', 'status' => 'available'],
            ], 'healthy'],
            'probe API all failed' => [[
                'eips_count' => 2,
                'election_info' => null,
            ], [
                ['target' => '192.0.2.1', 'status' => 'unavailable'],
                ['target' => '192.0.2.2', 'status' => 'unavailable'],
            ], 'all_failed'],
            'duplicate address is incomplete data' => [[
                'eips_count' => 2,
                'election_info' => ['list' => [$entry('EXAMPLE.COM.', 'available'), $entry('example.com', 'available')]],
            ], null, 'no_data'],
        ];
    }

    public function testProbeStatusesMatchesUuidAliasesAndNormalizesCase(): void
    {
        $rows = [
            ['uuid' => 'AAAAAAAA-AAAA-4AAA-8AAA-AAAAAAAAAAAA', 'list' => [['status' => 'available']]],
            ['dns_rule_uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'list' => [['status' => 'unavailable']]],
        ];

        self::assertSame(
            [['status' => 'available']],
            AxisNowAutomationService::probeStatusesForRule($rows, ' aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa ')
        );
        self::assertSame(
            [['status' => 'unavailable']],
            AxisNowAutomationService::probeStatusesForRule($rows, 'BBBBBBBB-BBBB-4BBB-8BBB-BBBBBBBBBBBB')
        );
    }

    public function testProbeStatusesDistinguishesMissingRuleFromEmptyList(): void
    {
        $rows = [['uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'list' => []]];

        self::assertSame([], AxisNowAutomationService::probeStatusesForRule($rows, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'));
        self::assertNull(AxisNowAutomationService::probeStatusesForRule($rows, 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'));
    }

    public function testEncodePoolUsesReadableJsonWithoutEscapedSlashes(): void
    {
        self::assertSame(
            '{"mode":"指定 EIP","endpoint":"https://example.com/path"}',
            AxisNowAutomationService::encodePool(['mode' => '指定 EIP', 'endpoint' => 'https://example.com/path'])
        );
    }

    public function testEncodePoolRejectsValuesJsonCannotRepresent(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('地址池序列化失败');

        AxisNowAutomationService::encodePool(['weight' => NAN]);
    }
}
