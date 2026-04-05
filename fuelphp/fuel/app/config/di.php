<?php

use DI\ContainerBuilder;
use Fuel\Core\Fuel;
use Infrastructure\EventSystem\FuelPhpOutboxRepository;
use Infrastructure\EventSystem\FuelPhpScheduledEventRepository;
use Psr\Container\ContainerInterface;
use SharedKernel\Domain\Cache\CacheInterface;
use SharedKernel\Domain\Environment;
use SharedKernel\Domain\EventSystem\AsyncEventProcessorInterface;
use SharedKernel\Domain\EventSystem\DomainEventCollectorInterface;
use SharedKernel\Domain\EventSystem\EventFactoryInterface;
use SharedKernel\Domain\EventSystem\ListenerProviderInterface;
use SharedKernel\Domain\EventSystem\OutboxEventProcessorInterface;
use SharedKernel\Domain\EventSystem\ScheduledEventProcessorInterface;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Domain\Redis\RedisClientInterface;
use SharedKernel\Domain\Storage\StorageInterface;
use SharedKernel\Infrastructure\Cache\CacheFactory;
use SharedKernel\Infrastructure\EventSystem\SqsAsyncEventProcessorFactory;
use SharedKernel\Infrastructure\EventSystem\DomainEventCollector;
use SharedKernel\Infrastructure\EventSystem\EventFactoryFactory;
use SharedKernel\Infrastructure\EventSystem\ListenerProviderFactory;
use SharedKernel\Infrastructure\EventSystem\OutboxEventProcessor;
use SharedKernel\Infrastructure\EventSystem\ScheduledEventProcessor;
use SharedKernel\Infrastructure\Logger\LoggerFactory;
use SharedKernel\Infrastructure\Redis\RedisClientFactory;
use SharedKernel\Infrastructure\Storage\CdnUrlResolver;
use SharedKernel\Infrastructure\Storage\StorageFactory;

$containerBuilder = new ContainerBuilder();
$repositories = require APPPATH . 'config/repositories.php';

if (Fuel::$env !== Fuel::DEVELOPMENT) {
    $containerBuilder->enableCompilation(APPPATH . '/tmp');
    $containerBuilder->writeProxiesToFile(true, APPPATH . '/tmp/proxies');
}

$containerBuilder->addDefinitions(array_merge([
    Environment::class => DI\create(Environment::class)->constructor(Fuel::$env, getenv('TEST_ENV_ID') ?: null),
    LoggerInterface::class => DI\factory(LoggerFactory::class),
    RedisClientInterface::class => DI\factory(RedisClientFactory::class),
    CacheInterface::class => DI\factory(CacheFactory::class),
    CdnUrlResolver::class => DI\create(CdnUrlResolver::class)->constructor([
        'public/images/' => getenv('CDN_IMAGES_URL') ?: '',
    ]),
    StorageFactory::class => DI\autowire(StorageFactory::class),
    StorageInterface::class => DI\factory(function(ContainerInterface $c) {
        return $c->get(StorageFactory::class)->create();
    }),

    // Event System
    EventFactoryInterface::class => DI\factory(EventFactoryFactory::class),
    ListenerProviderInterface::class => DI\factory(ListenerProviderFactory::class),
    DomainEventCollectorInterface::class => DI\create(DomainEventCollector::class),

    // Event Repositories (FuelPHP-specific)
    FuelPhpOutboxRepository::class => DI\autowire(FuelPhpOutboxRepository::class),
    FuelPhpScheduledEventRepository::class => DI\autowire(FuelPhpScheduledEventRepository::class),

    // Event Processors (wired explicitly to their repositories)
    OutboxEventProcessorInterface::class => DI\create(OutboxEventProcessor::class)->constructor(
        DI\get(FuelPhpOutboxRepository::class),
        DI\get(ListenerProviderInterface::class),
        DI\get(LoggerInterface::class)
    ),
    ScheduledEventProcessorInterface::class => DI\create(ScheduledEventProcessor::class)->constructor(
        DI\get(FuelPhpScheduledEventRepository::class),
        DI\get(ListenerProviderInterface::class),
        DI\get(LoggerInterface::class)
    ),
    SqsAsyncEventProcessorFactory::class => DI\create(SqsAsyncEventProcessorFactory::class)->constructor(
        DI\get(Environment::class),
        DI\get(EventFactoryInterface::class),
        DI\get(ListenerProviderInterface::class),
        DI\get(LoggerInterface::class),
        getenv('SQS_ASYNC_EVENTS_QUEUE_URL') ?: '',
        getenv('AWS_REGION') ?: ''
    ),
    AsyncEventProcessorInterface::class => DI\factory(SqsAsyncEventProcessorFactory::class),
]), $repositories);

return $containerBuilder->build();
