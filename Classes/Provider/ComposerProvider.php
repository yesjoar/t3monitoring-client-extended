<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Provider;

use TYPO3\CMS\Core\Core\Environment;
use Yesjoar\T3monitoringClientExtended\Composer\RequirementsReader;
use Yesjoar\T3monitoringClientExtended\Configuration\Settings;
use Yesjoar\T3monitoringClientExtended\Report\Insight;

/**
 * Reports the version constraints of the root composer.json, so a monitoring server can tell
 * whether an available update is allowed by the constraint ("composer update" is enough) or
 * the constraint has to be changed ("composer require"). It delivers plain data and no messages.
 */
final class ComposerProvider extends AbstractProvider
{
    protected function section(): string
    {
        return 'composer';
    }

    protected function title(): string
    {
        return 'Composer';
    }

    protected function collect(Settings $settings, int $now): Insight
    {
        $requirements = Environment::isComposerMode()
            ? (new RequirementsReader())->read(Environment::getProjectPath() . '/composer.json')
            : null;

        return new Insight($requirements === null ? ['available' => false] : ['available' => true, ...$requirements]);
    }
}
