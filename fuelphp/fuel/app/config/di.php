<?php

use DI\ContainerBuilder;
use Fuel\Core\Fuel;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Infrastructure\Logger\MonologLogger;

$containerBuilder = new ContainerBuilder();
$repositories = require APPPATH . 'config/repositories.php';

if (Fuel::$env !== Fuel::DEVELOPMENT) {
    $containerBuilder->enableCompilation(APPPATH . '/tmp');
    $containerBuilder->writeProxiesToFile(true, APPPATH . '/tmp/proxies');
}

$containerBuilder->addDefinitions(array_merge([
    'env' => Fuel::$env,
    LoggerInterface::class => DI\create(MonologLogger::class)->constructor(DI\get('env')),
]), $repositories);

return $containerBuilder->build();
