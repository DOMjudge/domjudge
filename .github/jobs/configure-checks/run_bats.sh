#!/bin/bash

set -euxo pipefail

testsuite="$1"
export testsuite

for i in shared_pre $testsuite shared_post; do
    file=".github/jobs/configure-checks/${i}.bats"
    mkdir -p "/tmp/bats_logs/${i}"
    test_path="/home/runner/work/domjudge/domjudge" bats --print-output-on-failure --gather-test-outputs-in "/tmp/bats_logs/${i}" "$file"
done
