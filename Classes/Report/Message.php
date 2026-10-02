<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Report;

/**
 * A human readable message in the "extra" data of the client, which the t3monitoring server imports.
 */
final class Message
{
    public function __construct(
        public readonly Severity $severity,
        public readonly string $title,
        public readonly string $text,
    ) {}
}
