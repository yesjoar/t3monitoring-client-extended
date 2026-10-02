<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Provider;

use TYPO3\CMS\Core\Core\Environment;
use Yesjoar\T3monitoringClientExtended\Configuration\Settings;
use Yesjoar\T3monitoringClientExtended\Log\LogFileInspector;
use Yesjoar\T3monitoringClientExtended\Report\Insight;
use Yesjoar\T3monitoringClientExtended\Report\Message;
use Yesjoar\T3monitoringClientExtended\Report\Severity;

/**
 * Reports the errors written to the TYPO3 log files (var/log/typo3_*.log), e.g. uncaught
 * exceptions of the frontend, which do not show up in the system log.
 */
final class LogFileProvider extends AbstractProvider
{
    protected function section(): string
    {
        return 'logFiles';
    }

    protected function title(): string
    {
        return 'Log files';
    }

    protected function collect(Settings $settings, int $now): Insight
    {
        $periodHours = $settings->int('logFiles.periodHours', 24, 1);
        $report = (new LogFileInspector())->inspect(
            $this->logFiles(),
            $now - $periodHours * 3600,
            $settings->string('logFiles.minimumLevel', 'error'),
            $settings->int('logFiles.maxBytesPerFile', 1048576, 1024),
            $settings->int('logFiles.maxGroups', 10, 1),
            $this->normalizer($settings),
        );

        $summary = $report['summary'];
        unset($report['summary']);
        $messages = [];

        if ($report['entries'] > 0) {
            $messages[] = new Message(
                Severity::Warning,
                sprintf('Log files - %d error(s) in the last %d hours', $report['entries'], $periodHours),
                $summary
            );
        }

        return new Insight(['periodHours' => $periodHours, ...$report], $messages);
    }

    /**
     * The log files of the FileWriter, without the deprecation log.
     *
     * @return list<string>
     */
    private function logFiles(): array
    {
        $files = glob(Environment::getVarPath() . '/log/typo3_*.log') ?: [];

        return array_values(array_filter(
            $files,
            static fn(string $file): bool => !str_contains(basename($file), 'deprecations')
        ));
    }
}
