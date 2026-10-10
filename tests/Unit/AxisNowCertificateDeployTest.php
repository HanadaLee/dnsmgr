<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\lib\deploy\axisnow as AxisNowDeploy;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AxisNowCertificateDeployTest extends TestCase
{
    private static string $privateKey = '';
    private static string $originalCertificate = '';
    private static string $renewedCertificate = '';

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, self::$privateKey));
        $csr = openssl_csr_new(['commonName' => 'example.com'], $key, ['digest_alg' => 'sha256']);
        self::assertNotFalse($csr);
        foreach ([1 => 'originalCertificate', 2 => 'renewedCertificate'] as $serial => $property) {
            $certificate = openssl_csr_sign($csr, null, $key, 90, ['digest_alg' => 'sha256'], $serial);
            self::assertNotFalse($certificate);
            self::assertTrue(openssl_x509_export($certificate, self::${$property}));
        }
    }

    public function testRenewalUploadsANewCertificateAndSavesItsUuid(): void
    {
        $client = $this->client([
            ['uuid' => 'old-uuid', 'certificate' => self::$originalCertificate],
            ['uuid' => 'new-uuid'],
        ]);
        $info = ['uuid' => 'old-uuid'];

        $client->deploy(self::$renewedCertificate, self::$privateKey, [], $info);

        self::assertSame(['GET', 'POST'], array_column($client->requests, 'method'));
        self::assertSame('/client/v1/certificates/old-uuid', $client->requests[0]['path']);
        self::assertSame('/client/v1/certificates', $client->requests[1]['path']);
        self::assertSame('upload', $client->requests[1]['data']['type']);
        self::assertSame(self::$renewedCertificate, $client->requests[1]['data']['certificate']);
        self::assertSame(self::$privateKey, $client->requests[1]['data']['private_key']);
        self::assertSame(['uuid' => 'new-uuid'], $info);
    }

    public function testIdenticalCertificateIsReusedDespitePemFormattingDifferences(): void
    {
        $client = $this->client([
            ['uuid' => 'old-uuid', 'certificate' => str_replace("\n", "\r\n", self::$originalCertificate)],
        ]);
        $info = ['uuid' => 'old-uuid'];

        $client->deploy(self::$originalCertificate, self::$privateKey, [], $info);

        self::assertSame(['GET'], array_column($client->requests, 'method'));
        self::assertSame(['uuid' => 'old-uuid'], $info);
    }

    public function testFirstDeploymentUploadsACertificate(): void
    {
        $client = $this->client([['uuid' => 'new-uuid']]);
        $info = [];

        $client->deploy(self::$originalCertificate, self::$privateKey, [], $info);

        self::assertSame(['POST'], array_column($client->requests, 'method'));
        self::assertSame(['uuid' => 'new-uuid'], $info);
    }

    #[DataProvider('unverifiableCertificateProvider')]
    public function testMissingOrUnverifiableOldCertificateTriggersANewUpload($existing): void
    {
        $client = $this->client([$existing, ['uuid' => 'new-uuid']]);
        $info = ['uuid' => 'old-uuid'];

        $client->deploy(self::$renewedCertificate, self::$privateKey, [], $info);

        self::assertSame(['GET', 'POST'], array_column($client->requests, 'method'));
        self::assertSame(['uuid' => 'new-uuid'], $info);
    }

    public static function unverifiableCertificateProvider(): array
    {
        return [
            'certificate was removed' => [new Exception('not found')],
            'response contains metadata only' => [['uuid' => 'old-uuid', 'name' => 'example.com']],
            'response contains invalid certificate' => [['uuid' => 'old-uuid', 'certificate' => 'invalid PEM']],
        ];
    }

    #[DataProvider('failedUploadProvider')]
    public function testFailedUploadPreservesThePreviousUuid($uploadResult): void
    {
        $client = $this->client([
            ['uuid' => 'old-uuid', 'certificate' => self::$originalCertificate],
            $uploadResult,
        ]);
        $info = ['uuid' => 'old-uuid'];

        try {
            $client->deploy(self::$renewedCertificate, self::$privateKey, [], $info);
            self::fail('An unsuccessful upload must not be reported as successful.');
        } catch (Exception $e) {
            self::assertStringStartsWith('上传证书失败:', $e->getMessage());
        }

        self::assertSame(['uuid' => 'old-uuid'], $info);
        self::assertSame(['GET', 'POST'], array_column($client->requests, 'method'));
    }

    public static function failedUploadProvider(): array
    {
        return [
            'API error' => [new Exception('upload rejected')],
            'missing result' => [true],
            'missing UUID' => [[]],
            'empty UUID' => [['uuid' => '']],
        ];
    }

    private function client(array $responses): StubAxisNowCertificateDeploy
    {
        $client = new StubAxisNowCertificateDeploy(['token' => 'test-token']);
        $client->responses = $responses;
        return $client;
    }
}

final class StubAxisNowCertificateDeploy extends AxisNowDeploy
{
    public array $requests = [];
    public array $responses = [];

    protected function request($method, $path, $data = null)
    {
        $this->requests[] = compact('method', 'path', 'data');
        if ($this->responses === []) {
            throw new Exception('Unexpected AxisNow request');
        }
        $response = array_shift($this->responses);
        if ($response instanceof Exception) {
            throw $response;
        }
        return $response;
    }
}
