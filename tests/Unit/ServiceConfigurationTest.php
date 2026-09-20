<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\service\AxisNowService;
use app\service\CloudflareEnhanceService;
use Exception;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ServiceConfigurationTest extends TestCase
{
    public function testAxisNowRequiresToken(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('API 令牌不能为空');

        new AxisNowService(['token' => '   ']);
    }

    public function testAxisNowEmptyFiltersDoNotCallRemoteApi(): void
    {
        $service = new AxisNowService(['token' => 'token']);

        self::assertSame([], $service->listDnsRecordsByRuleUuids([]));
        self::assertSame([], $service->listLatestRuleEventsByRuleUuids(['', '  ']));
        self::assertSame([], $service->listProbeTaskStatusesByRuleUuids([]));
        self::assertSame([], $service->resolveSubscriptionProviders([]));
    }

    public function testCloudflareAuthenticationModeAndAccountConfiguration(): void
    {
        $token = new CloudflareEnhanceService(['apikey' => " token-with-space\n", 'account_id' => ' account ']);
        $globalKey = new CloudflareEnhanceService(['email' => 'admin@example.com', 'apikey' => str_repeat('a', 37)]);

        self::assertTrue($token->isApiTokenAuth());
        self::assertSame('account', $token->getConfiguredAccountId());
        self::assertFalse($globalKey->isApiTokenAuth());
        self::assertSame(['Authorization' => 'Bearer token-with-space', 'Content-Type' => 'application/json'], $this->invoke($token, 'buildHeaders', true));
        self::assertSame([
            'X-Auth-Email' => 'admin@example.com',
            'X-Auth-Key' => str_repeat('a', 37),
        ], $this->invoke($globalKey, 'buildHeaders', false));
    }

    public function testCloudflareGlobalKeyRequiresEmail(): void
    {
        $service = new CloudflareEnhanceService(['apikey' => str_repeat('a', 37), 'auth' => 0]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('缺少邮箱地址');
        $this->invoke($service, 'buildHeaders', false);
    }

    public function testCloudflareHostnameAndErrorNormalization(): void
    {
        $service = new CloudflareEnhanceService(['apikey' => 'token', 'auth' => 1]);

        self::assertSame('example.com', $this->invoke($service, 'normalizeHostname', ' Example.COM. '));
        self::assertSame('xn--fsqu00a.xn--0zwm56d', $this->invoke($service, 'normalizeHostname', '例子.测试.'));
        self::assertSame('', $this->invoke($service, 'normalizeHostname', ''));
        self::assertSame('first error', $this->invoke($service, 'extractErrorMessage', ['errors' => [['message' => ' first error ']]]));
        self::assertSame('message', $this->invoke($service, 'extractErrorMessage', ['messages' => [['message' => 'message']]]));
        self::assertSame('', $this->invoke($service, 'extractErrorMessage', []));
    }

    public function testCloudflareTunnelRejectsGlobalApiKeyMode(): void
    {
        $service = new CloudflareEnhanceService(['email' => 'admin@example.com', 'apikey' => str_repeat('a', 37), 'auth' => 0]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('仅支持 API 令牌认证');
        $this->invoke($service, 'assertTunnelSupported');
    }

    private function invoke(object $object, string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod($object, $method))->invoke($object, ...$arguments);
    }
}
