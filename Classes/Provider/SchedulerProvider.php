<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Provider;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Yesjoar\T3monitoringClientExtended\Configuration\Settings;
use Yesjoar\T3monitoringClientExtended\Report\Insight;
use Yesjoar\T3monitoringClientExtended\Report\Message;
use Yesjoar\T3monitoringClientExtended\Report\Severity;
use Yesjoar\T3monitoringClientExtended\Scheduler\SchedulerInspector;

/**
 * Reports whether the scheduler runs and which tasks failed, are overdue or stuck.
 */
final class SchedulerProvider extends AbstractProvider
{
    protected function section(): string
    {
        return 'scheduler';
    }

    protected function title(): string
    {
        return 'Scheduler';
    }

    protected function collect(Settings $settings, int $now): Insight
    {
        if (!ExtensionManagementUtility::isLoaded('scheduler')) {
            return new Insight(['available' => false]);
        }

        $inspector = new SchedulerInspector(
            GeneralUtility::makeInstance(ConnectionPool::class),
            GeneralUtility::makeInstance(Registry::class),
            $this->normalizer($settings),
        );

        $report = $inspector->inspect(
            $now,
            $settings->int('scheduler.overdueMinutes', 60, 1) * 60,
            $settings->int('scheduler.stuckMinutes', 240, 1) * 60,
        );

        $lastRunStatus = $this->lastRunStatus($settings, $report, $now);

        return new Insight(['available' => true, 'lastRunStatus' => $lastRunStatus, ...$report], [
            ...$this->lastRunMessages($lastRunStatus, $report, $now),
            ...$this->taskMessages($report),
        ]);
    }

    /**
     * Rates the last run of the scheduler: "unused" (no enabled tasks, so it is not expected to run),
     * "never", "ok", "warning" or "error" (by the configured number of minutes since the last run).
     *
     * @param array{lastRun: array{start: int, end: int, type: string}|null, tasks: array<string, int>} $report
     */
    private function lastRunStatus(Settings $settings, array $report, int $now): string
    {
        if ($report['tasks']['enabled'] === 0) {
            return 'unused';
        }

        if ($report['lastRun'] === null) {
            return 'never';
        }

        $minutes = $this->minutesSinceLastRun($report['lastRun'], $now);

        return match (true) {
            $minutes >= $settings->int('scheduler.lastRunErrorMinutes', 360, 1) => 'error',
            $minutes >= $settings->int('scheduler.lastRunWarningMinutes', 60, 1) => 'warning',
            default => 'ok',
        };
    }

    /**
     * @param array{start: int, end: int, type: string} $lastRun
     */
    private function minutesSinceLastRun(array $lastRun, int $now): int
    {
        return intdiv(max(0, $now - max($lastRun['start'], $lastRun['end'])), 60);
    }

    /**
     * @param array{lastRun: array{start: int, end: int, type: string}|null, tasks: array<string, int>} $report
     * @return list<Message>
     */
    private function lastRunMessages(string $lastRunStatus, array $report, int $now): array
    {
        if ($lastRunStatus === 'never') {
            return [new Message(
                Severity::Warning,
                'Scheduler - never ran',
                sprintf('%d task(s) are enabled, but the scheduler never ran. Check the cron job.', $report['tasks']['enabled'])
            )];
        }

        if ($report['lastRun'] === null || !in_array($lastRunStatus, ['warning', 'error'], true)) {
            return [];
        }

        return [new Message(
            $lastRunStatus === 'error' ? Severity::Error : Severity::Warning,
            'Scheduler - not running',
            sprintf(
                'The last run was %s ago (%s UTC). Check the cron job.',
                $this->duration($this->minutesSinceLastRun($report['lastRun'], $now)),
                gmdate('Y-m-d H:i', max($report['lastRun']['start'], $report['lastRun']['end']))
            )
        )];
    }

    /**
     * @param array{tasks: array<string, int>, problems: list<array<string, int|string>>} $report
     * @return list<Message>
     */
    private function taskMessages(array $report): array
    {
        $labels = [
            'failed' => [Severity::Error, 'Scheduler - %d failed task(s)'],
            'stuck' => [Severity::Warning, 'Scheduler - %d stuck task(s)'],
            'overdue' => [Severity::Warning, 'Scheduler - %d overdue task(s)'],
        ];
        $messages = [];

        foreach ($labels as $type => [$severity, $title]) {
            if ($report['tasks'][$type] === 0) {
                continue;
            }

            $lines = [];

            foreach ($report['problems'] as $problem) {
                if ($problem['type'] !== $type) {
                    continue;
                }

                $lines[] = trim(sprintf(
                    '#%d %s%s (since %s UTC)%s',
                    $problem['uid'],
                    $problem['task'],
                    $problem['description'] !== '' ? ' "' . $problem['description'] . '"' : '',
                    gmdate('Y-m-d H:i', (int)$problem['since']),
                    $problem['message'] !== '' ? ': ' . $problem['message'] : ''
                ));
            }

            $messages[] = new Message($severity, sprintf($title, $report['tasks'][$type]), implode("\n", $lines));
        }

        return $messages;
    }

    private function duration(int $minutes): string
    {
        return match (true) {
            $minutes >= 2880 => intdiv($minutes, 1440) . ' days',
            $minutes >= 120 => intdiv($minutes, 60) . ' hours',
            default => $minutes . ' minutes',
        };
    }
}
