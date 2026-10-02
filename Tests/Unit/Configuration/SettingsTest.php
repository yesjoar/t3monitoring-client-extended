<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Yesjoar\T3monitoringClientExtended\Configuration\Settings;

final class SettingsTest extends UnitTestCase
{
    #[Test]
    public function nestedValuesAreReadWithTheirType(): void
    {
        $settings = new Settings([
            'scheduler' => ['enabled' => '0', 'overdueMinutes' => '90'],
            'logFiles' => ['minimumLevel' => ' warning '],
            'ignoredMessages' => 'favicon.ico, ,robots.txt',
        ]);

        self::assertFalse($settings->bool('scheduler.enabled', true));
        self::assertSame(90, $settings->int('scheduler.overdueMinutes', 60));
        self::assertSame('warning', $settings->string('logFiles.minimumLevel', 'error'));
        self::assertSame(['favicon.ico', 'robots.txt'], $settings->list('ignoredMessages'));
    }

    #[Test]
    public function missingAndInvalidValuesFallBackToTheDefault(): void
    {
        $settings = new Settings(['scheduler' => ['enabled' => '', 'overdueMinutes' => 'soon', 'stuckMinutes' => '-5']]);

        self::assertTrue($settings->bool('scheduler.enabled', true));
        self::assertTrue($settings->bool('sysLog.enabled', true));
        self::assertSame(60, $settings->int('scheduler.overdueMinutes', 60));
        self::assertSame(1, $settings->int('scheduler.stuckMinutes', 240, 1));
        self::assertSame('error', $settings->string('logFiles.minimumLevel', 'error'));
        self::assertSame([], $settings->list('ignoredMessages'));
    }
}
