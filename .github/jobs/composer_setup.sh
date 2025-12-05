#!/bin/bash

set -euxo pipefail

. .github/jobs/ci_settings.sh

section_start "Configure PHP"
PHPVERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION."\n";')
export PHPVERSION
echo "$PHPVERSION" | tee -a "$ARTIFACTS"/phpversion.txt
section_end

section_start "Run composer"
cd webapp
composer install --no-scripts 2>&1 | tee -a "$ARTIFACTS/composer_log.txt"
section_end
