<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\lib\mail\Aliyun;
use app\lib\mail\Sendcloud;
use PHPUnit\Framework\TestCase;

final class MailClientTest extends TestCase
{
    public function testMailClientsRejectMissingCredentialsWithoutNetworkAccess(): void
    {
        self::assertFalse((new Aliyun('', ''))->send('user@example.com', 'subject', 'body', 'from@example.com', 'dnsmgr'));
        self::assertFalse((new Sendcloud('', ''))->send('user@example.com', 'subject', 'body', 'from@example.com', 'dnsmgr'));
    }
}
