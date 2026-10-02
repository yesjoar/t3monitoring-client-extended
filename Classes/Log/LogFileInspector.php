<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Log;

/**
 * Evaluates the entries of the TYPO3 log files (var/log/typo3_*.log, written by the FileWriter)
 * of a period. Only the end of each file is read, to limit the runtime on large files.
 */
final class LogFileInspector
{
    /**
     * PSR-3 levels, most severe first.
     */
    private const LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    /**
     * Line format of the FileWriter: "<RFC 2822 date> [LEVEL] request="<id>" component="<component>": <message> - <json>".
     */
    private const LINE_PATTERN = '/^(\w{3}, \d{2} \w{3} \d{4} \d{2}:\d{2}:\d{2} [+-]\d{4}) \[([A-Z]+)\] request="[^"]*" component="([^"]*)": (.*)$/';

    /**
     * @param list<string> $files absolute paths of the log files
     * @return array{entries: int, files: int, groups: list<array<string, int|string>>, truncated: bool, summary: string}
     */
    public function inspect(array $files, int $since, string $minimumLevel, int $maxBytesPerFile, int $maxGroups, MessageNormalizer $normalizer): array
    {
        $groups = new MessageGroups($normalizer);
        $maximumLevelIndex = array_search(strtolower($minimumLevel), self::LEVELS, true);
        $maximumLevelIndex = $maximumLevelIndex === false ? 3 : $maximumLevelIndex;
        $truncated = false;
        $readFiles = 0;

        foreach ($files as $file) {
            $modified = @filemtime($file);

            if ($modified === false || $modified < $since || !is_readable($file)) {
                continue;
            }

            $readFiles++;

            foreach ($this->tail($file, $maxBytesPerFile, $truncated) as $line) {
                if (preg_match(self::LINE_PATTERN, $line, $matches) !== 1) {
                    // Continuation of a multi-line entry (e.g. a stack trace).
                    continue;
                }

                $timestamp = strtotime($matches[1]);
                $level = strtolower($matches[2]);
                $levelIndex = array_search($level, self::LEVELS, true);

                if ($timestamp === false || $timestamp < $since || $levelIndex === false || $levelIndex > $maximumLevelIndex) {
                    continue;
                }

                $groups->add($this->message($matches[4]), $timestamp, ['level' => $level, 'component' => $matches[3]]);
            }
        }

        return [
            'entries' => $groups->total(),
            'files' => $readFiles,
            'groups' => $groups->top($maxGroups),
            'truncated' => $truncated,
            'summary' => $groups->summary($maxGroups),
        ];
    }

    /**
     * The lines at the end of a file. If the file is larger than the limit, the first
     * (incomplete) line is dropped.
     *
     * @return list<string>
     */
    private function tail(string $file, int $maxBytes, bool &$truncated): array
    {
        $size = @filesize($file);
        $handle = @fopen($file, 'rb');

        if ($size === false || $handle === false) {
            return [];
        }

        $maxBytes = max(1024, $maxBytes);
        $isPartial = $size > $maxBytes;

        if ($isPartial) {
            fseek($handle, -$maxBytes, SEEK_END);
            $truncated = true;
        }

        $content = (string)stream_get_contents($handle);
        fclose($handle);
        $lines = preg_split('/\R/', $content) ?: [];

        if ($isPartial) {
            array_shift($lines);
        }

        return $lines;
    }

    /**
     * Remove the JSON encoded context the FileWriter appends to the message.
     */
    private function message(string $message): string
    {
        $position = strpos($message, ' - {');

        return trim($position === false ? $message : substr($message, 0, $position));
    }
}
