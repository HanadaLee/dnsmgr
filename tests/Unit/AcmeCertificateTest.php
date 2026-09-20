<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\lib\acme\ACME_Exception;
use app\lib\acme\ACMECert;
use app\lib\CertHelper;
use Exception;
use PHPUnit\Framework\TestCase;

final class AcmeCertificateTest extends TestCase
{
    public static function tearDownAfterClass(): void
    {
        $randomFile = getcwd() . DIRECTORY_SEPARATOR . '.rnd';
        if (is_file($randomFile)) {
            unlink($randomFile);
        }
    }

    public function testRsaAndEcKeysCanBeGeneratedAndLoaded(): void
    {
        $client = $this->client();
        $rsa = $client->generateRSAKey(2048);
        $ec = $client->generateECKey('256');

        self::assertStringContainsString('BEGIN PRIVATE KEY', $rsa);
        self::assertStringContainsString('BEGIN PRIVATE KEY', $ec);
        $client->loadAccountKey($rsa);
        $client->loadAccountKey($ec);
    }

    public function testCsrAndAlpnCertificateContainRequestedDomains(): void
    {
        $client = $this->client();
        $key = $client->generateECKey('256');
        $csr = $client->generateCSR($key, ['example.com', '*.example.com']);
        $certificate = $client->generateALPNCertificate($key, 'example.com', str_repeat('ab', 32));

        self::assertStringContainsString('BEGIN CERTIFICATE REQUEST', $csr);
        self::assertSame(['example.com'], $client->getSAN($certificate));
        self::assertGreaterThan(0, $client->getRemainingDays($certificate));
        self::assertGreaterThan(0, $client->getRemainingPercent($certificate));

        $pfx = CertHelper::getPfx($certificate, $key, 'password');
        self::assertNotSame('', $pfx);
        self::assertTrue(openssl_pkcs12_read($pfx, $certificates, 'password'));
        self::assertArrayHasKey('cert', $certificates);
        self::assertArrayHasKey('pkey', $certificates);
    }

    public function testCertificateChainSplitting(): void
    {
        $client = $this->client();
        $key = $client->generateECKey('256');
        $certificate = $client->generateALPNCertificate($key, 'example.com', str_repeat('cd', 32));

        self::assertCount(2, $client->splitChain($certificate . "\n" . $certificate));
        self::assertSame([], $client->splitChain('not a certificate'));
    }

    public function testInvalidAccountKeyIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not load account key');

        $this->client()->loadAccountKey('invalid-key');
    }

    public function testLoggerAndAcmeExceptionMetadata(): void
    {
        $messages = [];
        $client = $this->client();
        $client->setLogger(static function (string $message) use (&$messages): void {
            $messages[] = $message;
        });
        $client->log('generated');

        self::assertSame(['generated'], $messages);
        $exception = new ACME_Exception('urn:example:error', 'failed', [['detail' => 'subproblem']]);
        self::assertSame('urn:example:error', $exception->getType());
        self::assertSame([['detail' => 'subproblem']], $exception->getSubproblems());
        self::assertSame('failed', $exception->getMessage());
    }

    private function client(): ACMECert
    {
        $client = new ACMECert('https://example.invalid/directory');
        $client->setLogger(false);
        return $client;
    }
}
