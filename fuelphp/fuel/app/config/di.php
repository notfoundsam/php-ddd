<?php

use DI\ContainerBuilder;
use Fuel\Core\Fuel;
use SharedKernel\Domain\Cache\CacheInterface;
use SharedKernel\Domain\Environment;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Domain\Redis\RedisClientInterface;
use SharedKernel\Infrastructure\Cache\CacheFactory;
use SharedKernel\Infrastructure\Logger\LoggerFactory;
use SharedKernel\Infrastructure\Redis\RedisClientFactory;

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
]), $repositories);

return $containerBuilder->build();
