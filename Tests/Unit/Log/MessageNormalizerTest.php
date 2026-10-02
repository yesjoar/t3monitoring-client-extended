<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Tests\Unit\Log;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Yesjoar\T3monitoringClientExtended\Log\MessageNormalizer;

final class MessageNormalizerTest extends UnitTestCase
{
    #[Test]
    public function queryStringsWithSecretsAreRemoved(): void
    {
        $message = 'Uncaught TYPO3 Exception. Requested URL: https://example.org/?eID=t3monitoring&secret=TopSecret123';

        $sanitized = (new MessageNormalizer())->sanitize($message);

        self::assertSame('Uncaught TYPO3 Exception. Requested URL: https://example.org/?…', $sanitized);
        self::assertStringNotContainsString('TopSecret123', $sanitized);
    }

    #[Test]
    #[TestWith(['Login of jane.doe@example.org failed', 'Login of <email> failed'])]
    #[TestWith(['Request from 192.168.10.20 blocked', 'Request from <ip> blocked'])]
    #[TestWith(['Request from 2001:0db8:85a3:0000:0000:8a2e:0370:7334 blocked', 'Request from <ip> blocked'])]
    #[TestWith(["Line one\n   line two", 'Line one line two'])]
    public function personalDataAndWhitespaceAreNormalized(string $message, string $expected): void
    {
        self::assertSame($expected, (new MessageNormalizer())->sanitize($message));
    }

    #[Test]
    public function longMessagesAreShortened(): void
    {
        $sanitized = (new MessageNormalizer())->sanitize(str_repeat('ä', 400));

        self::assertSame(300, mb_strlen($sanitized));
        self::assertStringEndsWith('…', $sanitized);
    }

    #[Test]
    public function messagesDifferingOnlyInNumbersAndIdsShareAGroupKey(): void
    {
        $normalizer = new MessageNormalizer();

        self::assertSame(
            $normalizer->groupKey('File /releases/12/public/a.php not found (request 4f3a9c0b1d2e)'),
            $normalizer->groupKey('File /releases/13/public/a.php not found (request 99aa77bb66cc)')
        );
        self::assertNotSame($normalizer->groupKey('File not found'), $normalizer->groupKey('Table not found'));
    }

    #[Test]
    public function messagesContainingAnIgnoredFragmentAreIgnored(): void
    {
        $normalizer = new MessageNormalizer(['favicon.ico', '']);

        self::assertTrue($normalizer->isIgnored('Page not found: /FAVICON.ICO'));
        self::assertFalse($normalizer->isIgnored('Page not found: /robots.txt'));
    }
}
