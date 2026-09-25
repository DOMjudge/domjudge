#!/usr/bin/env bash

# Run the given configure check suites in parallel, each in its own container
# of the base image. The suites run configure and make in the source
# tree, so every container works on a private copy of it, mounted at the
# same path as the checkout since the tests assert on that path.

set -uo pipefail

suites=("$@")
if [ ${#suites[@]} -eq 0 ]; then
    echo "Usage: $0 <suite>..."
    exit 1
fi

workdir=$(pwd)
logdir=/tmp/bats_logs
summary=${GITHUB_STEP_SUMMARY:-/dev/null}
durations=$(mktemp -d)

declare -A pids
for suite in "${suites[@]}"; do
    mkdir -p "$logdir/$suite"
    (
        start=$SECONDS
        docker run --name "$suite" -v "$logdir/$suite:/tmp/bats_logs" \
            -v "$workdir:/src:ro" -w "$workdir" baseimage \
            bash -c 'cp -a /src/. . && exec .github/jobs/configure-checks/run_bats.sh "$1"' \
            _ "$suite" > "$logdir/$suite/output.log" 2>&1
        rc=$?
        echo "$((SECONDS - start))" > "$durations/$suite"
        exit $rc
    ) &
    pids[$suite]=$!
done

failed=()
{
    echo "| Suite | Result | Duration |"
    echo "| --- | --- | --- |"
} >> "$summary"
for suite in "${suites[@]}"; do
    wait "${pids[$suite]}"
    rc=$?
    duration=$(cat "$durations/$suite" 2>/dev/null || echo "?")
    if [ "$rc" -eq 0 ]; then
        result=passed
    else
        result="FAILED (exit code $rc)"
        failed+=("$suite")
    fi
    echo "| $suite | $result | ${duration}s |" >> "$summary"
    echo "::group::$suite: $result after ${duration}s"
    cat "$logdir/$suite/output.log"
    echo "::endgroup::"
done

# Repeat the output of failed suites outside of a group, so it is
# visible without searching for it.
for suite in "${failed[@]}"; do
    echo "========== Output of failed suite $suite =========="
    cat "$logdir/$suite/output.log"
    echo "::error title=Configure check failed::Suite $suite failed, see its output above."
done

if [ ${#failed[@]} -gt 0 ]; then
    echo "Failed suites: ${failed[*]}"
    exit 1
fi
echo "All suites passed: ${suites[*]}"
