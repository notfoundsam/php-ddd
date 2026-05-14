<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Domain\Notification\Message;

use SharedKernel\Domain\Notification\Exception\InvalidSmsMessageException;
use SharedKernel\Domain\Notification\Message\SmsMessage;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\ValueObjects\PhoneNumber;

class SmsMessageTest extends TestCase
{
    public function testCreateWithMobile(): void
    {
        $sms = SmsMessage::create(new PhoneNumber('090-1234-5678'), 'Hello there');

        $this->assertSame('+819012345678', $sms->getPhoneNumber()->toInternationalFormat());
        $this->assertSame('Hello there', $sms->getMessage());
        $this->assertFalse($sms->hasStatusCallback());
    }

    public function testCreateRejectsLandline(): void
    {
        $this->expectException(InvalidSmsMessageException::class);

        SmsMessage::create(new PhoneNumber('03-1234-5678'), 'Hi');
    }

    public function testCreateRejectsEmptyBody(): void
    {
        $this->expectException(InvalidSmsMessageException::class);

        SmsMessage::create(new PhoneNumber('090-1234-5678'), '   ');
    }

    public function testWithStatusCallbackIsImmutable(): void
    {
        $original = SmsMessage::create(new PhoneNumber('090-1234-5678'), 'Hi');
        $withCallback = $original->withStatusCallback();

        $this->assertFalse($original->hasStatusCallback());
        $this->assertTrue($withCallback->hasStatusCallback());
    }
}
