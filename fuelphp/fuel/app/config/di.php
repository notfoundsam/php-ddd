<?php

use DI\ContainerBuilder;
use Fuel\Core\Fuel;

$containerBuilder = new ContainerBuilder();
$repositories = require APPPATH . 'config/repositories.php';

if (Fuel::$env !== Fuel::DEVELOPMENT) {
    $containerBuilder->enableCompilation(APPPATH . '/tmp');
    $containerBuilder->writeProxiesToFile(true, APPPATH . '/tmp/proxies');
}

$containerBuilder->addDefinitions(array_merge([
    'env' => Fuel::$env,
]), $repositories);

return $containerBuilder->build();
