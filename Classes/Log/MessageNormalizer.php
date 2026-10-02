<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Log;

/**
 * Prepares log messages for the monitoring: removes data that must not leave the
 * installation (query strings with secrets, email and IP addresses) and builds a key
 * under which equal messages are grouped.
 */
final class MessageNormalizer
{
    private const MAX_LENGTH = 300;

    /**
     * @param list<string> $ignoredFragments messages containing one of these fragments are ignored
     */
    public function __construct(private readonly array $ignoredFragments = []) {}

    public function isIgnored(string $message): bool
    {
        foreach ($this->ignoredFragments as $fragment) {
            if ($fragment !== '' && stripos($message, $fragment) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove personal data and secrets and limit the length.
     */
    public function sanitize(string $message): string
    {
        $message = (string)preg_replace(
            [
                // Query strings of URLs may contain secrets and personal data.
                '~(https?://[^\s"\'<>?]+)\?[^\s"\'<>]*~i',
                '~[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}~i',
                '~\b(?:\d{1,3}\.){3}\d{1,3}\b~',
                '~(?<![0-9A-F:])(?:[0-9A-F]{1,4}:){3,7}[0-9A-F]{1,4}(?![0-9A-F:])~i',
                '~\s+~',
            ],
            ['$1?…', '<email>', '<ip>', '<ip>', ' '],
            $message
        );

        $message = trim($message);

        return mb_strlen($message) > self::MAX_LENGTH ? mb_substr($message, 0, self::MAX_LENGTH - 1) . '…' : $message;
    }

    /**
     * Key under which messages that only differ in numbers and ids are grouped.
     */
    public function groupKey(string $message): string
    {
        return (string)preg_replace(
            ['~\b[0-9a-f]{8,}\b~i', '~\d+~'],
            '#',
            $this->sanitize($message)
        );
    }
}
