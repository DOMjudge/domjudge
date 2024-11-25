#!/bin/sh

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

mysql_log () {
    # shellcheck disable=SC2086
    echo "$1" | mysql ${2:-} | tee -a "$ARTIFACTS"/mysql.txt
}

show_phpinfo() {
    phpversion=$1
    section_start "Show the new PHP info"
    update-alternatives --set php /usr/bin/php"${phpversion}"
    php -v
    php -m
    section_end
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
