<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Yesjoar\T3monitoringClientExtended\Provider\SystemLogProvider;

final class SystemLogProviderTest extends AbstractProviderTestCase
{
    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            't3monitoring_client_extended' => [
                'sysLog' => ['failedLoginsWarning' => '2'],
                'ignoredMessages' => 'favicon.ico',
            ],
        ],
    ];

    private int $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = time();
    }

    private function logEntry(int $secondsAgo, int $error, string $details, int $type = 5, int $action = 0): void
    {
        $this->insert('sys_log', [
            'tstamp' => $this->now - $secondsAgo,
            'error' => $error,
            'type' => $type,
            'action' => $action,
            'details' => $details,
            'channel' => 'php',
            'userid' => 7,
            'IP' => '203.0.113.9',
            'log_data' => '{"user":"jane"}',
        ]);
    }

    #[Test]
    public function errorsOfThePeriodAreGrouped(): void
    {
        $this->logEntry(600, 2, 'Core: Exception handler (WEB): Table "tx_news_42" not found');
        $this->logEntry(300, 2, 'Core: Exception handler (WEB): Table "tx_news_43" not found');
        $this->logEntry(200, 1, 'Record could not be saved');
        $this->logEntry(150, 2, 'File /favicon.ico not found');
        $this->logEntry(100, 0, 'Record was updated');
        $this->logEntry(100000, 2, 'Older than the period');

        $data = (new SystemLogProvider())->get([]);
        $sysLog = $data['extended']['sysLog'];

        self::assertSame(24, $sysLog['periodHours']);
        self::assertSame(3, $sysLog['errors']);
        self::assertFalse($sysLog['truncated']);
        self::assertSame(
            [
                [
                    'channel' => 'php',
                    'message' => 'Core: Exception handler (WEB): Table "tx_news_43" not found',
                    'count' => 2,
                    'first' => $this->now - 600,
                    'last' => $this->now - 300,
                ],
                [
                    'channel' => 'php',
                    'message' => 'Record could not be saved',
                    'count' => 1,
                    'first' => $this->now - 200,
                    'last' => $this->now - 200,
                ],
            ],
            $sysLog['groups']
        );
        self::assertArrayNotHasKey('summary', $sysLog);

        $message = $data['extra']['warning']['System log - 3 error(s) in the last 24 hours'];
        self::assertStringContainsString('2× Core: Exception handler (WEB)', $message);
        self::assertStringNotContainsString('jane', json_encode($data, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('203.0.113.9', json_encode($data, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function manyFailedBackendLoginsAreReported(): void
    {
        $this->logEntry(500, 3, 'Login-attempt from ###IP###', 255, 3);
        $this->logEntry(400, 3, 'Login-attempt from ###IP###', 255, 3);
        $this->logEntry(300, 0, 'User %s logged in', 255, 1);

        $data = (new SystemLogProvider())->get([]);

        self::assertSame(2, $data['extended']['sysLog']['failedLogins']);
        self::assertSame(0, $data['extended']['sysLog']['errors']);
        self::assertArrayHasKey('System log - 2 failed backend logins in the last 24 hours', $data['extra']['warning']);
    }

    #[Test]
    public function aCleanLogProducesNoMessages(): void
    {
        $data = (new SystemLogProvider())->get(['core' => ['typo3Version' => '13.4.0']]);

        self::assertSame(['typo3Version' => '13.4.0'], $data['core']);
        self::assertSame(0, $data['extended']['sysLog']['errors']);
        self::assertArrayNotHasKey('extra', $data);
    }
}
