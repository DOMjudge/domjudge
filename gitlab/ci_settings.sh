#!/bin/bash

# Fail pipeline when variable is not set or individual command has an non-zero exitcode.
set -euo pipefail

shopt -s expand_aliases

# Shared constants between jobs
DIR=$(pwd)
GITSHA=$(git rev-parse HEAD || true)
export DIR
export GITSHA
export PS4='(${BASH_SOURCE}:${LINENO}): - [$?] $ '
export LOGFILE="/opt/domjudge/domserver/webapp/var/log/prod.log"

CCS_SPECS_PINNED_SHA1='6b11623d586500d11ec20b6c12a4908b44ff0e41'

# Shared storage for all artifacts
export GITLABARTIFACTS="$DIR/gitlabartifacts"
mkdir -p "$GITLABARTIFACTS"

# Functions to annotate the GitLab logs
alias trace_on='set -x'
alias trace_off='{ set +x; } 2>/dev/null'
function section_start_internal() {
    echo -e "section_start:$(date +%s):$2[collapsed=$1]\r\e[0K$3"
    trace_on
}
function section_end_internal() {
    echo -e "section_end:$(date +%s):$1\r\e[0K"
    trace_on
}
alias section_start_collap='trace_off ; section_start_internal true'
alias section_start='trace_off ; section_start_internal false'
alias section_end='trace_off ; section_end_internal '

function log_on_err() {
    echo -e "\\n\\n=======================================================\\n"
    echo "Symfony log:"
    if sudo test -f "$LOGFILE" ; then
        sudo cat "$LOGFILE"
    fi
}

function show_phpinfo() {
    phpversion=$1
    section_start_collap phpinfo "Show the new PHP info"
    update-alternatives --set php /usr/bin/php"${phpversion}"
    php -v
    php -m
    section_end phpinfo
}

# Show running command
set -x
