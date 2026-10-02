# t3monitoring_client_extended

Adds **scheduler, system log and log file insights** to the endpoint of
[t3monitoring_client](https://packagist.org/packages/t3monitor/t3monitoring_client), so your monitoring
shows right away when something goes wrong inside a TYPO3 installation:

- **Scheduler** – did the scheduler run recently? Which tasks failed, are overdue or stuck?
- **System log** – errors of `sys_log` in the last hours, grouped by message, and the number of failed backend logins.
- **Log files** – errors written to `var/log/typo3_*.log` (e.g. uncaught frontend exceptions that never show up in `sys_log`).

| | |
|---|---|
| Extension key | `t3monitoring_client_extended` |
| Composer name | `yesjoar/t3monitoring-client-extended` |
| TYPO3 | 12.4, 13.4, 14 |
| PHP | 8.2 – 8.5 |
| Requires | `t3monitor/t3monitoring_client` 11 |

## Installation

```bash
composer require yesjoar/t3monitoring-client-extended
```

There is nothing else to set up: the extension registers its providers in t3monitoring_client. The next request
to `?eID=t3monitoring&secret=…` contains the additional data.

## What the endpoint delivers

The result is delivered twice, so it is useful for the t3monitoring server as well as for other monitoring servers:

1. **Messages in `extra`** (`warning` / `danger`), the groups the [t3monitoring](https://github.com/georgringer/t3monitoring)
   server imports and shows for a client, e.g. `Scheduler - 2 failed task(s)` or
   `Log files - 14 error(s) in the last 24 hours`. They work without any change of the server.
2. **Structured data in `extended`** for monitoring servers that want to evaluate it:

```json
{
  "extended": {
    "version": 1,
    "scheduler": {
      "available": true,
      "lastRunStatus": "ok",
      "lastRun": { "start": 1790000000, "end": 1790000004, "type": "cron" },
      "tasks": { "total": 8, "enabled": 6, "disabled": 2, "failed": 1, "overdue": 0, "stuck": 0 },
      "problems": [
        {
          "uid": 12,
          "type": "failed",
          "task": "Vendor\\Extension\\Task\\ImportTask",
          "description": "Import products",
          "since": 1789999000,
          "message": "Could not reach https://api.example.org/?… (#1700000000)"
        }
      ]
    },
    "sysLog": {
      "periodHours": 24,
      "errors": 3,
      "failedLogins": 0,
      "groups": [
        { "channel": "php", "message": "Core: Exception handler (WEB): …", "count": 2, "first": 1789990000, "last": 1789995000 }
      ],
      "truncated": false
    },
    "logFiles": {
      "periodHours": 24,
      "entries": 14,
      "files": 1,
      "groups": [
        { "level": "error", "component": "TYPO3.CMS.Frontend", "message": "…", "count": 14, "first": 1789990000, "last": 1789995000 }
      ],
      "truncated": false
    }
  }
}
```

All times are Unix timestamps. `scheduler.lastRunStatus` rates the last run by the configured thresholds:
`ok`, `warning`, `error`, `never` (tasks are enabled, but the scheduler never ran) or `unused` (no enabled tasks). `version` is increased on incompatible changes of the format. If a provider
fails, the endpoint keeps working: the error is reported in `extended.errors.<provider>` and as a warning in `extra`.

## Configuration

*Admin Tools → Settings → Extension Configuration → t3monitoring_client_extended*

| Setting | Default | Description |
|---|---|---|
| `scheduler.enabled` | 1 | Report the scheduler |
| `scheduler.lastRunWarningMinutes` | 60 | Warning if the scheduler did not run for this long |
| `scheduler.lastRunErrorMinutes` | 360 | Error if the scheduler did not run for this long |
| `scheduler.overdueMinutes` | 60 | An enabled task is overdue if its planned execution is older |
| `scheduler.stuckMinutes` | 240 | A task is stuck if it is marked as running for longer |
| `sysLog.enabled` | 1 | Report errors of `sys_log` |
| `sysLog.periodHours` | 24 | Period that is evaluated |
| `sysLog.maxGroups` | 10 | Number of different errors that are reported |
| `sysLog.failedLoginsWarning` | 50 | Warning from this number of failed backend logins (0 = off) |
| `logFiles.enabled` | 1 | Report errors of the log files |
| `logFiles.periodHours` | 24 | Period that is evaluated |
| `logFiles.maxGroups` | 10 | Number of different errors that are reported |
| `logFiles.minimumLevel` | error | Minimum PSR-3 level (`warning` … `emergency`) |
| `logFiles.maxBytesPerFile` | 1048576 | Only the end of each log file is read |
| `ignoredMessages` | | Comma separated text fragments; matching entries are skipped |

## Privacy and security

The data leaves the installation, so it is reduced on purpose:

- No user names, IP addresses or record data of `sys_log` are read; only the message of an entry.
- Query strings of URLs (which may contain secrets), email and IP addresses are removed from all messages.
- Messages are limited to 300 characters; equal messages are grouped.
- Scheduler tasks are never unserialized, so no code of task classes is executed.

The endpoint itself is protected by t3monitoring_client (secret and allowed IPs).

## Development

The checks run in Docker with the images of the TYPO3 core testing:

```bash
Build/Scripts/test.sh              # TYPO3 13.4, PHP 8.3: coding standards, PHPStan, unit and functional tests
Build/Scripts/test.sh 12.4 8.2
Build/Scripts/test.sh 14.0 8.4 functional
```

### Release

The extension is distributed via Packagist only.

1. Set the version in `ext_emconf.php` and in `composer.json` (`extra.typo3/cms.version`), update `CHANGELOG.md`.
2. Create and push a tag with the version, e.g. `git tag -a 1.0.0 -m "First stable release" && git push --tags`.
   Packagist picks up the tag through its GitHub hook.

## License

GPL-2.0-or-later. Developed by [Kai Seliger](https://hikai.de).
