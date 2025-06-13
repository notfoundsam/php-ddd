<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\Event;

use LogicException;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Aggregate\AggregateRoot;
use SharedKernel\Domain\Event\EventInterface;
use SharedKernel\Domain\Event\OutboxEventInterface;
use SharedKernel\Domain\Event\PostCommitEventInterface;
use SharedKernel\Domain\Event\TransactionalEventInterface;
use SharedKernel\Infrastructure\Event\EventManager;

class EventManagerTest extends TestCase
{
    private EventManager $eventManager;

    protected function setUp(): void
    {
        $this->eventManager = new EventManager();
    }

    public function testPushTransactionalEvent(): void
    {
        // Arrange
        $event = $this->createMock(TransactionalEventInterface::class);

        // Act
        $this->eventManager->push($event);
        $events = $this->eventManager->pullTransactionalEvents();

        // Assert
        $this->assertCount(1, $events);
        $this->assertContainsEquals($event, $events);
        $this->assertEmpty($this->eventManager->pullTransactionalEvents());
    }

    public function testPushPostCommitEvent(): void
    {
        // Arrange
        $event = $this->createMock(PostCommitEventInterface::class);

        // Act
        $this->eventManager->push($event);
        $events = $this->eventManager->pullPostCommitEvents();

        // Assert
        $this->assertCount(1, $events);
        $this->assertContainsEquals($event, $events);
        $this->assertEmpty($this->eventManager->pullPostCommitEvents());
    }

    public function testPushOutboxEvent(): void
    {
        // Arrange
        $event = $this->createMock(OutboxEventInterface::class);

        // Act
        $this->eventManager->push($event);
        $events = $this->eventManager->pullOutboxEvents();

        // Assert
        $this->assertCount(1, $events);
        $this->assertContainsEquals($event, $events);
        $this->assertEmpty($this->eventManager->pullOutboxEvents());
    }

    public function testPushUnclassifiedEventThrowsException(): void
    {
        // Arrange
        $event = $this->createMock(EventInterface::class);

        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/^Unclassified event\:/');

        // Act
        $this->eventManager->push($event);
    }

    public function testCollectFromAggregate(): void
    {
        // Arrange
        $transactionalEvent = $this->createMock(TransactionalEventInterface::class);
        $postCommitEvent = $this->createMock(PostCommitEventInterface::class);
        $outboxEvent = $this->createMock(OutboxEventInterface::class);

        $aggregate = $this->createMock(AggregateRoot::class);
        $aggregate->method('releaseEvents')
            ->willReturn([$transactionalEvent, $postCommitEvent, $outboxEvent]);

        // Act
        $this->eventManager->collectFromAggregate($aggregate);

        // Assert
        $transactionalEvents = $this->eventManager->pullTransactionalEvents();
        $this->assertCount(1, $transactionalEvents);
        $this->assertContainsEquals($transactionalEvent, $transactionalEvents);

        $postCommitEvents = $this->eventManager->pullPostCommitEvents();
        $this->assertCount(1, $postCommitEvents);
        $this->assertContainsEquals($postCommitEvent, $postCommitEvents);

        $outboxEvents = $this->eventManager->pullOutboxEvents();
        $this->assertCount(1, $outboxEvents);
        $this->assertContainsEquals($outboxEvent, $outboxEvents);
    }

    public function testPullMethodsClearEvents(): void
    {
        // Arrange
        $transactionalEvent = $this->createMock(TransactionalEventInterface::class);
        $postCommitEvent = $this->createMock(PostCommitEventInterface::class);
        $outboxEvent = $this->createMock(OutboxEventInterface::class);

        $this->eventManager->push($transactionalEvent);
        $this->eventManager->push($postCommitEvent);
        $this->eventManager->push($outboxEvent);

        // Act & Assert
        // First pull should return the events
        $this->assertCount(1, $this->eventManager->pullTransactionalEvents());
        $this->assertCount(1, $this->eventManager->pullPostCommitEvents());
        $this->assertCount(1, $this->eventManager->pullOutboxEvents());

        // Second pull should return empty iterables
        $this->assertEmpty($this->eventManager->pullTransactionalEvents());
        $this->assertEmpty($this->eventManager->pullPostCommitEvents());
        $this->assertEmpty($this->eventManager->pullOutboxEvents());
    }

    public function testMultipleEventsOfSameType(): void
    {
        // Arrange
        $transactionalEvent1 = $this->createMock(TransactionalEventInterface::class);
        $transactionalEvent2 = $this->createMock(TransactionalEventInterface::class);

        // Act
        $this->eventManager->push($transactionalEvent1);
        $this->eventManager->push($transactionalEvent2);
        $events = $this->eventManager->pullTransactionalEvents();

        // Assert
        $this->assertCount(2, $events);
        $this->assertContainsEquals($transactionalEvent1, $events);
        $this->assertContainsEquals($transactionalEvent2, $events);
    }
}
