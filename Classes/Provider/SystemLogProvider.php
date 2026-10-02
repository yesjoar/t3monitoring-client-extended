<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Provider;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Yesjoar\T3monitoringClientExtended\Configuration\Settings;
use Yesjoar\T3monitoringClientExtended\Log\SystemLogInspector;
use Yesjoar\T3monitoringClientExtended\Report\Insight;
use Yesjoar\T3monitoringClientExtended\Report\Message;
use Yesjoar\T3monitoringClientExtended\Report\Severity;

/**
 * Reports the errors of the system log (sys_log) and the number of failed backend logins.
 */
final class SystemLogProvider extends AbstractProvider
{
    protected function section(): string
    {
        return 'sysLog';
    }

    protected function title(): string
    {
        return 'System log';
    }

    protected function collect(Settings $settings, int $now): Insight
    {
        $periodHours = $settings->int('sysLog.periodHours', 24, 1);
        $inspector = new SystemLogInspector(GeneralUtility::makeInstance(ConnectionPool::class));
        $report = $inspector->inspect(
            $now - $periodHours * 3600,
            $settings->int('sysLog.maxGroups', 10, 1),
            $this->normalizer($settings),
        );

        $summary = $report['summary'];
        unset($report['summary']);
        $messages = [];

        if ($report['errors'] > 0) {
            $messages[] = new Message(
                Severity::Warning,
                sprintf('System log - %d error(s) in the last %d hours', $report['errors'], $periodHours),
                $summary
            );
        }

        $failedLoginsWarning = $settings->int('sysLog.failedLoginsWarning', 50);

        if ($failedLoginsWarning > 0 && $report['failedLogins'] >= $failedLoginsWarning) {
            $messages[] = new Message(
                Severity::Warning,
                sprintf('System log - %d failed backend logins in the last %d hours', $report['failedLogins'], $periodHours),
                'An unusual number of failed logins can indicate an attack on the backend login.'
            );
        }

        return new Insight(['periodHours' => $periodHours, ...$report], $messages);
    }
}
