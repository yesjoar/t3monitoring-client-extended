<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Report;

/**
 * Result of a provider: structured data for monitoring servers that understand it
 * and messages for all others.
 */
final class Insight
{
    /**
     * @param array<string, mixed> $report
     * @param list<Message> $messages
     */
    public function __construct(
        public readonly array $report,
        public readonly array $messages = [],
    ) {}
}
