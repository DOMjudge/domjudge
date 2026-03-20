#!/bin/sh

testsuite="$1"

files=".github/jobs/configure-checks/all.bats"

# shellcheck disable=SC2086
test_path="/home/runner/work/domjudge/domjudge" bats --print-output-on-failure --gather-test-outputs-in /tmp/bats_logs $files
