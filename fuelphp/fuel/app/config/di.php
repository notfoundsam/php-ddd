<?php

use DI\ContainerBuilder;
use Fuel\Core\Fuel;
use Psr\Container\ContainerInterface;
use SharedKernel\Domain\Cache\CacheInterface;
use SharedKernel\Domain\Environment;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Domain\Redis\RedisClientInterface;
use SharedKernel\Domain\Storage\StorageInterface;
use SharedKernel\Infrastructure\Cache\CacheFactory;
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
]), $repositories);

return $containerBuilder->build();
