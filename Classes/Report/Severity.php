<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Report;

/**
 * Severities of messages in the "extra" data of t3monitoring_client. The values are the group
 * keys the t3monitoring server imports ("info", "warning" and "danger").
 */
enum Severity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'danger';
}
