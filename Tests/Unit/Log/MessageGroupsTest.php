<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Tests\Unit\Log;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Yesjoar\T3monitoringClientExtended\Log\MessageGroups;
use Yesjoar\T3monitoringClientExtended\Log\MessageNormalizer;

final class MessageGroupsTest extends UnitTestCase
{
    #[Test]
    public function equalMessagesAreGroupedAndOrderedByFrequency(): void
    {
        $groups = new MessageGroups(new MessageNormalizer(['ignore me']));
        $groups->add('Record 12 not found', 1000, ['level' => 'error']);
        $groups->add('Disk full', 1500, ['level' => 'critical']);
        $groups->add('Record 37 not found', 2000, ['level' => 'error']);
        $groups->add('Please ignore me', 2500, ['level' => 'error']);
        $groups->add('  ', 2600, ['level' => 'error']);

        self::assertSame(3, $groups->total());
        self::assertSame(
            [
                ['level' => 'error', 'message' => 'Record 37 not found', 'count' => 2, 'first' => 1000, 'last' => 2000],
                ['level' => 'critical', 'message' => 'Disk full', 'count' => 1, 'first' => 1500, 'last' => 1500],
            ],
            $groups->top(10)
        );
        self::assertSame("2× Record 37 not found\n1× Disk full", $groups->summary(10));
        self::assertCount(1, $groups->top(1));
    }

    #[Test]
    public function theSameMessageWithADifferentContextIsASeparateGroup(): void
    {
        $groups = new MessageGroups(new MessageNormalizer());
        $groups->add('Timeout', 1000, ['level' => 'error']);
        $groups->add('Timeout', 1000, ['level' => 'warning']);

        self::assertCount(2, $groups->top(10));
    }
}
