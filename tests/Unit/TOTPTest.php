<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\lib\TOTP;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TOTPTest extends TestCase
{
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function testKnownOtpVectors(): void
    {
        $totp = TOTP::create(self::RFC_SECRET);

        self::assertTrue($totp->verify('755224', 0));
        self::assertTrue($totp->verify('287082', 59));
        self::assertFalse($totp->verify('000000', 59));
    }

    public function testVerificationWindowAcceptsAdjacentTimeStep(): void
    {
        $totp = TOTP::create(self::RFC_SECRET);

        self::assertFalse($totp->verify('359152', 59));
        self::assertTrue($totp->verify('359152', 59, 1));
    }

    public function testNegativeTimestampIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timestamp must be at least 0.');

        TOTP::create(self::RFC_SECRET)->verify('000000', -1);
    }

    public function testProvisioningUriContainsIssuerLabelAndSecret(): void
    {
        $totp = TOTP::create(self::RFC_SECRET);
        $totp->setIssuer('Example Inc');
        $totp->setLabel('alice@example.com');

        self::assertSame(
            'otpauth://totp/Example%20Inc%3Aalice%40example.com?issuer=Example%20Inc&secret=' . self::RFC_SECRET,
            $totp->getProvisioningUri()
        );
    }

    public function testProvisioningUriRequiresLabelWithoutColon(): void
    {
        $totp = TOTP::create(self::RFC_SECRET);
        $totp->setLabel('tenant:alice');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Label must not contain a colon.');

        $totp->getProvisioningUri();
    }

    public function testGeneratedSecretIsBase32Encoded(): void
    {
        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', TOTP::create()->getSecret());
    }
}
