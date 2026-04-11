<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\CqrsMessageBus\Decorators;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryBusInterface;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Infrastructure\CqrsMessageBus\Decorators\QueryLoggerDecorator;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestQuery;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestQueryResponse;

class QueryLoggerDecoratorTest extends TestCase
{
    public function testLogsSuccessfulQueryAtDebugLevel(): void
    {
        $inner = $this->createMock(QueryBusInterface::class);
        $inner->method('dispatch')
            ->willReturn(new TestQueryResponse('data'));

        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects($this->once())
            ->method('debug')
            ->with(
                'Query executed successfully',
                $this->callback(function (array $context): bool {
                    return $context['message_type'] === 'query'
                        && $context['message_class'] === TestQuery::class
                        && $context['success'] === true
                        && isset($context['execution_time_ms']);
                })
            );

        $decorator = new QueryLoggerDecorator($inner, $logger);
        $response = $decorator->dispatch(new TestQuery('abc'));

        $this->assertInstanceOf(TestQueryResponse::class, $response);
    }

    public function testLogsErrorAndRethrowsException(): void
    {
        $exception = new RuntimeException('Query failed');

        $inner = $this->createMock(QueryBusInterface::class);
        $inner->method('dispatch')
            ->willThrowException($exception);

        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Query execution failed',
                $this->callback(function (array $context): bool {
                    return $context['success'] === false
                        && $context['error'] === 'Query failed'
                        && $context['error_class'] === RuntimeException::class;
                })
            );

        $decorator = new QueryLoggerDecorator($inner, $logger);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Query failed');

        $decorator->dispatch(new TestQuery('abc'));
    }
}
