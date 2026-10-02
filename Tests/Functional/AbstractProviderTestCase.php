<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Tests\Functional;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractProviderTestCase extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['extensionmanager', 'install', 'reports', 'scheduler'];

    protected array $testExtensionsToLoad = [
        't3monitor/t3monitoring_client',
        'yesjoar/t3monitoring-client-extended',
    ];

    /**
     * @param array<string, mixed> $values
     */
    protected function insert(string $table, array $values): void
    {
        $this->getConnectionPool()->getConnectionForTable($table)->insert($table, $values);
    }
}
