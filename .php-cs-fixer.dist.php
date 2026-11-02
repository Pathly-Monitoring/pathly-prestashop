<?php

require_once __DIR__ . '/tools/cs/vendor/autoload.php';

$config = new PrestaShop\CodingStandards\CsFixer\Config();

/** @var Symfony\Component\Finder\Finder $finder */
$finder = $config->setUsingCache(true)->getFinder();
$finder->in(__DIR__)->exclude(['vendor', 'tools', 'build', 'translations']);

return $config;
