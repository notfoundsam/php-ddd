<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Domain\Throttle\Events;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Security\UserType;
use SharedKernel\Domain\Throttle\Events\CqrsThrottleWarningEvent;

class CqrsThrottleWarningEventTest extends TestCase
{
    public function testCreateWithAllFields(): void
    {
        $event = CqrsThrottleWarningEvent::create(
            'command',
            'CreateOrderCommand',
            'user:42',
            25,
            '192.168.1.100',
            UserType::CUSTOMER
        );

        $this->assertSame('command', $event->getMessageType());
        $this->assertSame('CreateOrderCommand', $event->getMessageClass());
        $this->assertSame('user:42', $event->getIdentifier());
        $this->assertSame(25, $event->getRequestCount());
        $this->assertSame('192.168.1.100', $event->getClientIp());
        $this->assertSame(UserType::CUSTOMER, $event->getUserType());
    }

    public function testCreateWithNullUserTypeForAnonymous(): void
    {
        $event = CqrsThrottleWarningEvent::create(
            'query',
            'SearchQuery',
            'ip:10.0.0.1',
            15,
            '10.0.0.1',
            null
        );

        $this->assertNull($event->getUserType());
        $this->assertSame('10.0.0.1', $event->getClientIp());
    }

    public function testSerializeDeserializeRoundtrip(): void
    {
        $event = CqrsThrottleWarningEvent::create(
            'command',
            'ContactFormCommand',
            'ip:192.168.1.50',
            18,
            '192.168.1.50',
            null
        );

        $serialized = $event->serialize();
        $restored = CqrsThrottleWarningEvent::fromPayload($serialized);

        $this->assertSame($event->getMessageType(), $restored->getMessageType());
        $this->assertSame($event->getMessageClass(), $restored->getMessageClass());
        $this->assertSame($event->getIdentifier(), $restored->getIdentifier());
        $this->assertSame($event->getRequestCount(), $restored->getRequestCount());
        $this->assertSame($event->getClientIp(), $restored->getClientIp());
        $this->assertSame($event->getUserType(), $restored->getUserType());
    }

    public function testFromPayloadWithMissingNewFieldsBackwardCompatible(): void
    {
        $payload = [
            'message_type' => 'command',
            'message_class' => 'OldCommand',
            'identifier' => 'user:1',
            'request_count' => 10,
        ];

        $event = CqrsThrottleWarningEvent::fromPayload($payload);

        $this->assertSame('', $event->getClientIp());
        $this->assertNull($event->getUserType());
    }
}
