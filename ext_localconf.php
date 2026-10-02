<?php

declare(strict_types=1);

use Yesjoar\T3monitoringClientExtended\Provider\ComposerProvider;
use Yesjoar\T3monitoringClientExtended\Provider\LogFileProvider;
use Yesjoar\T3monitoringClientExtended\Provider\SchedulerProvider;
use Yesjoar\T3monitoringClientExtended\Provider\SystemLogProvider;

defined('TYPO3') || exit('Access denied.');

$GLOBALS['TYPO3_CONF_VARS']['EXT']['t3monitoring_client']['provider'][] = SchedulerProvider::class;
$GLOBALS['TYPO3_CONF_VARS']['EXT']['t3monitoring_client']['provider'][] = SystemLogProvider::class;
$GLOBALS['TYPO3_CONF_VARS']['EXT']['t3monitoring_client']['provider'][] = LogFileProvider::class;
$GLOBALS['TYPO3_CONF_VARS']['EXT']['t3monitoring_client']['provider'][] = ComposerProvider::class;
