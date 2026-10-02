<?php

declare(strict_types=1);

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->setUnsupportedPhpVersionAllowed(true);
$config->getFinder()
    ->in(dirname(__DIR__, 2))
    ->exclude(['.Build', 'var']);
$config->setCacheFile(dirname(__DIR__, 2) . '/.Build/.php-cs-fixer.cache');

return $config;
