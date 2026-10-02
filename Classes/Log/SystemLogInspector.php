<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Log;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Evaluates the errors of the system log (sys_log) of a period. Only the message templates
 * are read ("details"), never user names, IP addresses or record data.
 */
final class SystemLogInspector
{
    private const TABLE = 'sys_log';

    /**
     * sys_log.error: 1 = user error, 2 = system error (see TYPO3\CMS\Core\SysLog\Error).
     */
    private const ERROR_VALUES = [1, 2];

    /**
     * sys_log.type 255 = login, action 3 = attempt, error 3 = security notice.
     */
    private const LOGIN_TYPE = 255;
    private const LOGIN_ATTEMPT_ACTION = 3;
    private const SECURITY_NOTICE = 3;

    /**
     * Upper limit of entries that are grouped, newest first.
     */
    private const MAX_ROWS = 5000;

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @return array{errors: int, failedLogins: int, groups: list<array<string, int|string>>, truncated: bool, summary: string}
     */
    public function inspect(int $since, int $maxGroups, MessageNormalizer $normalizer): array
    {
        $groups = new MessageGroups($normalizer);
        $rows = 0;

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder
            ->select('tstamp', 'details', 'channel')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->gte('tstamp', $queryBuilder->createNamedParameter($since, Connection::PARAM_INT)),
                $queryBuilder->expr()->in('error', self::ERROR_VALUES)
            )
            ->orderBy('tstamp', 'DESC')
            ->setMaxResults(self::MAX_ROWS)
            ->executeQuery();

        while ($row = $result->fetchAssociative()) {
            $rows++;
            $groups->add((string)$row['details'], (int)$row['tstamp'], ['channel' => (string)$row['channel']]);
        }

        return [
            'errors' => $groups->total(),
            'failedLogins' => $this->countFailedLogins($since),
            'groups' => $groups->top($maxGroups),
            'truncated' => $rows >= self::MAX_ROWS,
            'summary' => $groups->summary($maxGroups),
        ];
    }

    private function countFailedLogins(int $since): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        return (int)$queryBuilder
            ->count('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('type', self::LOGIN_TYPE),
                $queryBuilder->expr()->eq('action', self::LOGIN_ATTEMPT_ACTION),
                $queryBuilder->expr()->eq('error', self::SECURITY_NOTICE),
                $queryBuilder->expr()->gte('tstamp', $queryBuilder->createNamedParameter($since, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();
    }
}
