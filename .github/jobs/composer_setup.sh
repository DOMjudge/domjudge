#!/bin/sh

set -eux

# Store artifacts/logs
export ARTIFACTS="/tmp/artifacts"
mkdir -p "$ARTIFACTS"

# Functions to annotate the Github actions logs
trace_on () {
    set -x
}

trace_off () {
    {
        set +x
    } 2>/dev/null
}

section_start_internal () {
    echo "::group::$1"
    trace_on
}

section_end_internal () {
    echo "::endgroup::"
    trace_on
}

section_start () {
    if [ "$#" -ne 1 ]; then
        echo "Only 1 argument is needed for GHA, 2 was needed for GitLab."
        exit 1
    fi
    trace_off
    section_start_internal "$1"
}

section_end () {
    trace_off
    section_end_internal
}

section_start "Configure PHP"
PHPVERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION."\n";')
export PHPVERSION
echo "$PHPVERSION" | tee -a "$ARTIFACTS"/phpversion.txt
section_end

section_start "Run composer"
composer install --no-scripts 2>&1 | tee -a "$ARTIFACTS/composer_log.txt"
section_end
