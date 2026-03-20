#!/bin/sh

testsuite="$1"

files=""
for i in shared_pre $testsuite shared_post; do
  files=".github/jobs/configure-checks/${i}.bats $files"
done

# shellcheck disable=SC2086
test_path="/home/runner/work/domjudge/domjudge" bats --print-output-on-failure --gather-test-outputs-in /tmp/bats_logs $files
