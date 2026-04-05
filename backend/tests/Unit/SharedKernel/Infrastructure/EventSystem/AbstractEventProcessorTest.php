<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\EventSystem;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SharedKernel\Domain\EventSystem\EventListenerInterface;
use SharedKernel\Domain\EventSystem\EventRepositoryInterface;
use SharedKernel\Domain\EventSystem\ListenerProviderInterface;
use SharedKernel\Domain\EventSystem\ReceivedEvent;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Infrastructure\EventSystem\AbstractEventProcessor;
use Tests\Fixtures\SharedKernel\EventSystem\OrderCreatedEvent;

class AbstractEventProcessorTest extends TestCase
{
    private EventRepositoryInterface $repository;
    private ListenerProviderInterface $listenerProvider;
    private LoggerInterface $logger;
    private AbstractEventProcessor $processor;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EventRepositoryInterface::class);
        $this->listenerProvider = $this->createMock(ListenerProviderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $repo = $this->repository;
        $provider = $this->listenerProvider;
        $log = $this->logger;

        $this->processor = new class ($repo, $provider, $log) extends AbstractEventProcessor {
            private EventRepositoryInterface $repo;

            public function __construct(
                EventRepositoryInterface $repo,
                ListenerProviderInterface $listenerProvider,
                LoggerInterface $logger
            ) {
                parent::__construct($listenerProvider, $logger);
                $this->repo = $repo;
            }

            protected function getRepository(): EventRepositoryInterface
            {
                return $this->repo;
            }

            protected function getEventTypeLabel(): string
            {
                return 'Test';
            }
        };
    }

    public function testProcessEventsReturnsCount(): void
    {
        $event = new OrderCreatedEvent('order-1');
        $received1 = new ReceivedEvent($event, 'ctx-1');
        $received2 = new ReceivedEvent($event, 'ctx-2');

        $this->repository->method('getUnprocessedEvents')->willReturn([$received1, $received2]);
        $this->listenerProvider->method('getListenersForEvent')->willReturn([]);

        $count = $this->processor->processEvents();

        $this->assertSame(2, $count);
    }

    public function testMarksEventAsProcessedWhenAllListenersSucceed(): void
    {
        $event = new OrderCreatedEvent('order-1');
        $received = new ReceivedEvent($event, 'ctx-1');

        $listener = $this->createMock(EventListenerInterface::class);
        $listener->expects($this->once())->method('handle')->with($event);

        $this->repository->method('getUnprocessedEvents')->willReturn([$received]);
        $this->listenerProvider->method('getListenersForEvent')->willReturn([$listener]);

        $this->repository->expects($this->once())->method('markProcessed')->with($received);
        $this->repository->expects($this->never())->method('markFailed');

        $this->processor->processEvents();
    }

    public function testMarksEventAsFailedWhenListenerThrows(): void
    {
        $event = new OrderCreatedEvent('order-1');
        $received = new ReceivedEvent($event, 'ctx-1');

        $listener = $this->createMock(EventListenerInterface::class);
        $listener->method('handle')->willThrowException(new RuntimeException('fail'));

        $this->repository->method('getUnprocessedEvents')->willReturn([$received]);
        $this->listenerProvider->method('getListenersForEvent')->willReturn([$listener]);

        $this->repository->expects($this->never())->method('markProcessed');
        $this->repository->expects($this->once())->method('markFailed');

        $this->processor->processEvents();
    }

    public function testAllListenersCalledEvenIfOneThrows(): void
    {
        $event = new OrderCreatedEvent('order-1');
        $received = new ReceivedEvent($event, 'ctx-1');

        $failingListener = $this->createMock(EventListenerInterface::class);
        $failingListener->method('handle')->willThrowException(new RuntimeException('fail'));

        $succeedingListener = $this->createMock(EventListenerInterface::class);
        $succeedingListener->expects($this->once())->method('handle')->with($event);

        $this->repository->method('getUnprocessedEvents')->willReturn([$received]);
        $this->listenerProvider->method('getListenersForEvent')
            ->willReturn([$failingListener, $succeedingListener]);

        $this->processor->processEvents();
    }

    public function testProcessEventsReturnsZeroWhenNoEvents(): void
    {
        $this->repository->method('getUnprocessedEvents')->willReturn([]);

        $count = $this->processor->processEvents();

        $this->assertSame(0, $count);
    }

    public function testMarksProcessedWhenNoListenersRegistered(): void
    {
        $event = new OrderCreatedEvent('order-1');
        $received = new ReceivedEvent($event, 'ctx-1');

        $this->repository->method('getUnprocessedEvents')->willReturn([$received]);
        $this->listenerProvider->method('getListenersForEvent')->willReturn([]);

        $this->repository->expects($this->once())->method('markProcessed');

        $this->processor->processEvents();
    }
}
