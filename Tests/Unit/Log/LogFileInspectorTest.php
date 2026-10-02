<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Tests\Unit\Log;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Yesjoar\T3monitoringClientExtended\Log\LogFileInspector;
use Yesjoar\T3monitoringClientExtended\Log\MessageNormalizer;

final class LogFileInspectorTest extends UnitTestCase
{
    private const NOW = 1790000000;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . '/t3monitoring_client_extended_' . bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory . '/*') ?: []);
        rmdir($this->directory);

        parent::tearDown();
    }

    /**
     * A line as written by the FileWriter of TYPO3.
     */
    private function line(int $secondsAgo, string $level, string $message, string $component = 'TYPO3.CMS.Core.Error.ErrorHandler'): string
    {
        return sprintf('%s [%s] request="abc123" component="%s": %s', date('r', self::NOW - $secondsAgo), $level, $component, $message);
    }

    /**
     * @param list<string> $lines
     */
    private function logFile(string $name, array $lines, int $modifiedSecondsAgo = 0): string
    {
        $file = $this->directory . '/' . $name;
        file_put_contents($file, implode("\n", $lines) . "\n");
        touch($file, self::NOW - $modifiedSecondsAgo);

        return $file;
    }

    /**
     * @param list<string> $files
     * @return array{entries: int, files: int, groups: list<array<string, int|string>>, truncated: bool, summary: string}
     */
    private function inspect(array $files, string $minimumLevel = 'error', int $maxBytes = 1048576): array
    {
        return (new LogFileInspector())->inspect($files, self::NOW - 86400, $minimumLevel, $maxBytes, 10, new MessageNormalizer());
    }

    #[Test]
    public function errorsOfThePeriodAreGroupedWithoutTheirContext(): void
    {
        $file = $this->logFile('typo3_a.log', [
            $this->line(90000, 'ERROR', 'Too old'),
            $this->line(600, 'ERROR', 'Core: Exception handler (WEB): Call to undefined method in line 12 - {"exception":"secret context"}'),
            '#0 /var/www/vendor/foo.php(12): bar()',
            $this->line(300, 'ERROR', 'Core: Exception handler (WEB): Call to undefined method in line 99'),
            $this->line(200, 'WARNING', 'Only a warning'),
            $this->line(100, 'CRITICAL', 'Database is gone', 'TYPO3.CMS.Core.Database'),
        ]);

        $report = $this->inspect([$file]);

        self::assertSame(3, $report['entries']);
        self::assertSame(1, $report['files']);
        self::assertFalse($report['truncated']);
        self::assertSame(
            [
                [
                    'level' => 'error',
                    'component' => 'TYPO3.CMS.Core.Error.ErrorHandler',
                    'message' => 'Core: Exception handler (WEB): Call to undefined method in line 99',
                    'count' => 2,
                    'first' => self::NOW - 600,
                    'last' => self::NOW - 300,
                ],
                [
                    'level' => 'critical',
                    'component' => 'TYPO3.CMS.Core.Database',
                    'message' => 'Database is gone',
                    'count' => 1,
                    'first' => self::NOW - 100,
                    'last' => self::NOW - 100,
                ],
            ],
            $report['groups']
        );
    }

    #[Test]
    public function theMinimumLevelIsConfigurable(): void
    {
        $file = $this->logFile('typo3_a.log', [
            $this->line(200, 'WARNING', 'Only a warning'),
            $this->line(100, 'NOTICE', 'Only a notice'),
        ]);

        self::assertSame(0, $this->inspect([$file])['entries']);
        self::assertSame(1, $this->inspect([$file], 'warning')['entries']);
    }

    #[Test]
    public function filesNotModifiedInThePeriodAreSkipped(): void
    {
        $file = $this->logFile('typo3_old.log', [$this->line(100, 'ERROR', 'Entry in a stale file')], 90000);

        $report = $this->inspect([$file, $this->directory . '/missing.log']);

        self::assertSame(0, $report['files']);
        self::assertSame(0, $report['entries']);
    }

    #[Test]
    public function onlyTheEndOfLargeFilesIsRead(): void
    {
        $lines = [];

        for ($index = 0; $index < 200; $index++) {
            $lines[] = $this->line(1000 - $index, 'ERROR', 'Problem number ' . $index);
        }

        $report = $this->inspect([$this->logFile('typo3_large.log', $lines)], 'error', 2048);

        self::assertTrue($report['truncated']);
        self::assertGreaterThan(0, $report['entries']);
        self::assertLessThan(200, $report['entries']);
        self::assertSame(self::NOW - 801, $report['groups'][0]['last']);
    }
}
