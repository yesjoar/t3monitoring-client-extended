<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Yesjoar\T3monitoringClientExtended\Provider\SchedulerProvider;

final class SchedulerProviderTest extends AbstractProviderTestCase
{
    private const TASK_CLASS = 'Vendor\\Extension\\Task\\ImportTask';

    private int $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = time();
    }

    /**
     * @param array<string, mixed> $values
     */
    private function task(array $values = []): void
    {
        $task = [
            'disable' => 0,
            'deleted' => 0,
            'description' => '',
            'nextexecution' => $this->now + 300,
            'lastexecution_time' => $this->now - 300,
            'lastexecution_failure' => '',
            'serialized_executions' => '',
            // The stored object must not be unserialized; the class does not exist.
            'serialized_task_object' => sprintf('O:%d:"%s":0:{}', strlen(self::TASK_CLASS), self::TASK_CLASS),
            ...$values,
        ];

        if ((new Typo3Version())->getMajorVersion() >= 14) {
            $task['tasktype'] = self::TASK_CLASS;
        }

        $this->insert('tx_scheduler_task', $task);
    }

    private function lastRun(int $secondsAgo): void
    {
        GeneralUtility::makeInstance(Registry::class)->set('tx_scheduler', 'lastRun', [
            'start' => $this->now - $secondsAgo - 5,
            'end' => $this->now - $secondsAgo,
            'type' => 'cron',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function collect(): array
    {
        return (new SchedulerProvider())->get([]);
    }

    #[Test]
    public function aHealthySchedulerIsReportedWithoutMessages(): void
    {
        $this->lastRun(120);
        $this->task();
        $this->task(['disable' => 1, 'lastexecution_failure' => 'Disabled tasks are not reported']);
        $this->task(['deleted' => 1]);

        $data = $this->collect();

        self::assertSame(1, $data['extended']['version']);
        self::assertTrue($data['extended']['scheduler']['available']);
        self::assertSame(
            ['total' => 2, 'enabled' => 1, 'disabled' => 1, 'failed' => 0, 'overdue' => 0, 'stuck' => 0],
            $data['extended']['scheduler']['tasks']
        );
        self::assertSame('ok', $data['extended']['scheduler']['lastRunStatus']);
        self::assertSame('cron', $data['extended']['scheduler']['lastRun']['type']);
        self::assertSame($this->now - 120, $data['extended']['scheduler']['lastRun']['end']);
        self::assertSame([], $data['extended']['scheduler']['problems']);
        self::assertArrayNotHasKey('extra', $data);
    }

    #[Test]
    public function failedOverdueAndStuckTasksAreReported(): void
    {
        $this->lastRun(60);
        $this->task([
            'description' => 'Import products',
            'lastexecution_failure' => json_encode(['code' => 1700000000, 'message' => 'Could not reach https://api.example.org/?token=abc']),
        ]);
        $this->task(['lastexecution_failure' => serialize(['code' => 0, 'message' => 'Legacy format'])]);
        $this->task(['nextexecution' => $this->now - 7200]);
        $this->task(['serialized_executions' => serialize([$this->now - 18000]), 'nextexecution' => $this->now - 18000]);
        $this->task(['serialized_executions' => serialize([$this->now - 60])]);

        $data = $this->collect();
        $scheduler = $data['extended']['scheduler'];

        self::assertSame(
            ['total' => 5, 'enabled' => 5, 'disabled' => 0, 'failed' => 2, 'overdue' => 1, 'stuck' => 1],
            $scheduler['tasks']
        );
        self::assertSame(['failed', 'failed', 'overdue', 'stuck'], array_column($scheduler['problems'], 'type'));
        self::assertSame(self::TASK_CLASS, $scheduler['problems'][0]['task']);
        self::assertSame('Import products', $scheduler['problems'][0]['description']);
        self::assertSame('Could not reach https://api.example.org/?… (#1700000000)', $scheduler['problems'][0]['message']);
        self::assertSame('Legacy format', $scheduler['problems'][1]['message']);
        self::assertSame($this->now - 7200, $scheduler['problems'][2]['since']);
        self::assertSame($this->now - 18000, $scheduler['problems'][3]['since']);

        self::assertArrayHasKey('Scheduler - 2 failed task(s)', $data['extra']['danger']);
        self::assertStringContainsString('Import products', $data['extra']['danger']['Scheduler - 2 failed task(s)']);
        self::assertStringNotContainsString('token=abc', $data['extra']['danger']['Scheduler - 2 failed task(s)']);
        self::assertArrayHasKey('Scheduler - 1 overdue task(s)', $data['extra']['warning']);
        self::assertArrayHasKey('Scheduler - 1 stuck task(s)', $data['extra']['warning']);
    }

    #[Test]
    public function aSchedulerThatStoppedRunningIsReported(): void
    {
        $this->task();

        $data = $this->collect();
        self::assertSame('never', $data['extended']['scheduler']['lastRunStatus']);
        self::assertArrayHasKey('Scheduler - never ran', $data['extra']['warning']);

        $this->lastRun(2 * 3600);
        $data = $this->collect();
        self::assertSame('warning', $data['extended']['scheduler']['lastRunStatus']);
        self::assertArrayHasKey('Scheduler - not running', $data['extra']['warning']);

        $this->lastRun(12 * 3600);
        $data = $this->collect();
        self::assertSame('error', $data['extended']['scheduler']['lastRunStatus']);
        self::assertArrayHasKey('Scheduler - not running', $data['extra']['danger']);
        self::assertStringContainsString('12 hours', $data['extra']['danger']['Scheduler - not running']);
    }

    #[Test]
    public function aSchedulerWithoutEnabledTasksIsNotExpectedToRun(): void
    {
        $this->task(['disable' => 1]);

        $data = $this->collect();

        self::assertSame('unused', $data['extended']['scheduler']['lastRunStatus']);
        self::assertArrayNotHasKey('extra', $data);
    }
}
