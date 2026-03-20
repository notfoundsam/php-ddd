<?php

use DI\ContainerBuilder;
use Fuel\Core\Fuel;
use SharedKernel\Domain\Environment;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Infrastructure\Logger\LoggerFactory;

$containerBuilder = new ContainerBuilder();
$repositories = require APPPATH . 'config/repositories.php';

if (Fuel::$env !== Fuel::DEVELOPMENT) {
    $containerBuilder->enableCompilation(APPPATH . '/tmp');
    $containerBuilder->writeProxiesToFile(true, APPPATH . '/tmp/proxies');
}

$containerBuilder->addDefinitions(array_merge([
    Environment::class => DI\create(Environment::class)->constructor(Fuel::$env, getenv('TEST_ENV_ID') ?: null),
    LoggerInterface::class => DI\factory(LoggerFactory::class),
]), $repositories);

return $containerBuilder->build();
