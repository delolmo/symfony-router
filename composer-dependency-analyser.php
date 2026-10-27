<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

$config = new Configuration();

$config->disableComposerAutoloadPathScan();

$config->addPathToScan(__DIR__ . '/src', isDev: false);

$config->addPathToScan(__DIR__ . '/tests', isDev: true);

return $config;
