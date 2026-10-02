<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Log;

/**
 * Collects log entries and groups equal messages, keeping the count, the time of the
 * first and the last occurrence and the text of the most recent entry.
 */
final class MessageGroups
{
    /**
     * @var array<string, array{message: string, count: int, first: int, last: int, context: array<string, string>}>
     */
    private array $groups = [];

    private int $total = 0;

    public function __construct(private readonly MessageNormalizer $normalizer) {}

    /**
     * @param array<string, string> $context additional values of the group, e.g. the log level
     */
    public function add(string $message, int $timestamp, array $context = []): void
    {
        if (trim($message) === '' || $this->normalizer->isIgnored($message)) {
            return;
        }

        $this->total++;
        $key = implode('|', $context) . '|' . $this->normalizer->groupKey($message);

        if (!isset($this->groups[$key])) {
            $this->groups[$key] = [
                'message' => $this->normalizer->sanitize($message),
                'count' => 0,
                'first' => $timestamp,
                'last' => $timestamp,
                'context' => $context,
            ];
        }

        // The most recent entry represents the group.
        if ($timestamp > $this->groups[$key]['last']) {
            $this->groups[$key]['message'] = $this->normalizer->sanitize($message);
        }

        $this->groups[$key]['count']++;
        $this->groups[$key]['first'] = min($this->groups[$key]['first'], $timestamp);
        $this->groups[$key]['last'] = max($this->groups[$key]['last'], $timestamp);
    }

    /**
     * Number of entries that were added and not ignored.
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * The most frequent groups; groups with the same count are ordered by their last occurrence.
     *
     * @return list<array<string, int|string>>
     */
    public function top(int $limit): array
    {
        $groups = array_values($this->groups);

        usort(
            $groups,
            static fn(array $a, array $b): int => [$b['count'], $b['last']] <=> [$a['count'], $a['last']]
        );

        return array_map(
            static fn(array $group): array => [
                ...$group['context'],
                'message' => $group['message'],
                'count' => $group['count'],
                'first' => $group['first'],
                'last' => $group['last'],
            ],
            array_slice($groups, 0, max(1, $limit))
        );
    }

    /**
     * Summary of the top groups for a message, one group per line ("23× message").
     */
    public function summary(int $limit): string
    {
        return implode("\n", array_map(
            static fn(array $group): string => sprintf('%d× %s', $group['count'], $group['message']),
            $this->top($limit)
        ));
    }
}
