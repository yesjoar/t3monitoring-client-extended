<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Scheduler;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Registry;
use Yesjoar\T3monitoringClientExtended\Log\MessageNormalizer;

/**
 * Reads the state of the scheduler from the database: the last run and tasks that failed,
 * are overdue or stuck.
 *
 * The stored task objects are never unserialized (that would execute code of the task classes
 * and fails if an extension was removed); the task type is read from the "tasktype" column
 * (TYPO3 14) or the class name inside the serialized string (TYPO3 12 and 13).
 */
final class SchedulerInspector
{
    private const TABLE = 'tx_scheduler_task';

    private const MAX_PROBLEMS = 25;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly Registry $registry,
        private readonly MessageNormalizer $normalizer,
    ) {}

    /**
     * @return array{
     *     lastRun: array{start: int, end: int, type: string}|null,
     *     tasks: array{total: int, enabled: int, disabled: int, failed: int, overdue: int, stuck: int},
     *     problems: list<array{uid: int, type: string, task: string, description: string, since: int, message: string}>
     * }
     */
    public function inspect(int $now, int $overdueSeconds, int $stuckSeconds): array
    {
        $counts = ['total' => 0, 'enabled' => 0, 'disabled' => 0, 'failed' => 0, 'overdue' => 0, 'stuck' => 0];
        $problems = [];

        foreach ($this->tasks() as $task) {
            $counts['total']++;

            if ((bool)($task['disable'] ?? false)) {
                $counts['disabled']++;

                continue;
            }

            $counts['enabled']++;
            $failure = $this->failureMessage($task['lastexecution_failure'] ?? null);
            $runningSince = $this->runningSince($task['serialized_executions'] ?? null);
            $nextExecution = (int)($task['nextexecution'] ?? 0);

            $problem = match (true) {
                $failure !== null => ['failed', (int)($task['lastexecution_time'] ?? 0), $failure],
                $runningSince !== null && $runningSince < $now - $stuckSeconds => ['stuck', $runningSince, ''],
                $runningSince === null && $nextExecution > 0 && $nextExecution < $now - $overdueSeconds => ['overdue', $nextExecution, ''],
                default => null,
            };

            if ($problem === null) {
                continue;
            }

            $counts[$problem[0]]++;
            $problems[] = [
                'uid' => (int)$task['uid'],
                'type' => $problem[0],
                'task' => $this->taskType($task),
                'description' => $this->normalizer->sanitize((string)($task['description'] ?? '')),
                'since' => $problem[1],
                'message' => $problem[2],
            ];
        }

        return [
            'lastRun' => $this->lastRun(),
            'tasks' => $counts,
            'problems' => array_slice($problems, 0, self::MAX_PROBLEMS),
        ];
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function tasks(): iterable
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        // Disabled tasks are counted as well, so the default restrictions (TCA since TYPO3 14) must not apply.
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('deleted', 0))
            ->orderBy('uid')
            ->executeQuery()
            ->iterateAssociative();
    }

    /**
     * @return array{start: int, end: int, type: string}|null
     */
    private function lastRun(): ?array
    {
        $lastRun = $this->registry->get('tx_scheduler', 'lastRun');

        if (!is_array($lastRun) || empty($lastRun['start'])) {
            return null;
        }

        return [
            'start' => (int)$lastRun['start'],
            'end' => (int)($lastRun['end'] ?? 0),
            'type' => (string)($lastRun['type'] ?? ''),
        ];
    }

    /**
     * The failure is stored as JSON or as a serialized array with the exception data, depending on the TYPO3 version.
     */
    private function failureMessage(mixed $failure): ?string
    {
        $failure = trim($this->stringValue($failure));

        if ($failure === '') {
            return null;
        }

        $data = json_decode($failure, true);

        if (!is_array($data)) {
            $data = @unserialize($failure, ['allowed_classes' => false]);
        }

        $message = is_array($data) && is_scalar($data['message'] ?? null) ? (string)$data['message'] : $failure;
        $code = is_array($data) && !empty($data['code']) && is_scalar($data['code']) ? ' (#' . $data['code'] . ')' : '';

        return $this->normalizer->sanitize($message) . $code;
    }

    /**
     * Start time of the oldest execution that is still marked as running.
     */
    private function runningSince(mixed $executions): ?int
    {
        $executions = $this->stringValue($executions);

        if ($executions === '') {
            return null;
        }

        $executions = @unserialize($executions, ['allowed_classes' => false]);
        $timestamps = is_array($executions) ? array_filter(array_map('intval', $executions)) : [];

        return $timestamps === [] ? null : min($timestamps);
    }

    /**
     * @param array<string, mixed> $task
     */
    private function taskType(array $task): string
    {
        $type = trim((string)($task['tasktype'] ?? ''));

        if ($type === '' && preg_match('/^O:\d+:"([^"]+)"/', $this->stringValue($task['serialized_task_object'] ?? null), $matches) === 1) {
            $type = $matches[1];
        }

        return $type === '' ? 'unknown' : $type;
    }

    /**
     * Some database drivers return binary columns as streams.
     */
    private function stringValue(mixed $value): string
    {
        if (is_resource($value)) {
            return (string)stream_get_contents($value);
        }

        return is_scalar($value) ? (string)$value : '';
    }
}
