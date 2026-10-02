#!/usr/bin/env bash

#
# Runs the checks of the extension in a Docker container, against one TYPO3 and PHP version.
#
#   Build/Scripts/test.sh                 TYPO3 13.4 with PHP 8.3
#   Build/Scripts/test.sh 12.4 8.2        TYPO3 12.4 with PHP 8.2
#   Build/Scripts/test.sh 14.0 8.4 unit   Only one step: cs, phpstan, unit or functional
#   SKIP_INSTALL=1 Build/Scripts/test.sh 13.4 8.3 functional
#
# Uses the PHP images of the TYPO3 core testing (ghcr.io/typo3/core-testing-php*).
# Composer's blocking of versions with security advisories is disabled for the test
# installation: the public releases of TYPO3 versions in ELTS are affected by advisories.
#

set -euo pipefail

TYPO3_VERSION="${1:-13.4}"
PHP_VERSION="${2:-8.3}"
STEP="${3:-all}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
IMAGE="ghcr.io/typo3/core-testing-php${PHP_VERSION//./}:latest"

run() {
    docker run --rm -t \
        -v "${ROOT}:/app" -w /app \
        -e COMPOSER_HOME=/app/.Build/.composer \
        -e COMPOSER_NO_SECURITY_BLOCKING=1 \
        -e typo3DatabaseDriver=pdo_sqlite \
        --user "$(id -u):$(id -g)" \
        "${IMAGE}" "$@"
}

echo "==> TYPO3 ${TYPO3_VERSION}, PHP ${PHP_VERSION}"

# SKIP_INSTALL=1 reuses the packages installed by the previous run.
if [ -z "${SKIP_INSTALL:-}" ]; then
    # A clean installation: Composer plugins of another TYPO3 version break an in-place update.
    rm -rf "${ROOT}/composer.lock" "${ROOT}/.Build/vendor" "${ROOT}/.Build/public" "${ROOT}/.Build/bin"
    # "--with" restricts the TYPO3 version for this run only, composer.json stays untouched.
    run composer update --no-progress --no-interaction \
        --with "typo3/cms-core:^${TYPO3_VERSION}" --with "typo3/cms-scheduler:^${TYPO3_VERSION}"
fi

case "${STEP}" in
    cs) run composer cs ;;
    phpstan) run composer phpstan ;;
    unit) run composer test:unit ;;
    functional) run composer test:functional ;;
    all)
        run composer cs
        run composer phpstan
        run composer test:unit
        run composer test:functional
        ;;
    *) echo "Unknown step: ${STEP}" >&2; exit 1 ;;
esac
