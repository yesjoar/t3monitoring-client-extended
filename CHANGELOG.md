# Changelog

All notable changes to this extension are documented in this file. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), the versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Scheduler provider: last run of the scheduler, failed, overdue and stuck tasks.
- System log provider: grouped errors of `sys_log` and the number of failed backend logins.
- Log file provider: grouped errors of `var/log/typo3_*.log`.
- Structured data below the key `extended` (format version 1) and messages in `extra` (`warning` / `danger`).
