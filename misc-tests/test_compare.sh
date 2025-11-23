#!/bin/bash
# shellcheck disable=SC2317

set -e

COMPARE_EXECUTABLE=./compare_test_executable

fail() {
    fail=1
    msg="$1"
    echo -e "\e[31mFAIL: $msg\e[0m" >&2
    return 1
}

exec_compare() {
    local expected_exit_code=$1
    shift
    local test_name=$1
    shift

    echo -n "- $test_name "

    # Create input files
    echo "does not matter" > judge_in.txt
    echo "$1" > judge_ans.txt
    echo "$2" > team_out.txt
    mkdir -p feedback
    shift 2

    # Run the compare program
    EXIT_CODE=0
    $COMPARE_EXECUTABLE judge_in.txt judge_ans.txt feedback "$@" < team_out.txt || EXIT_CODE=$?

    # Check expected result
    if [ "$EXIT_CODE" -eq "$expected_exit_code" ]; then
        echo -e "\e[32m✔\e[0m" >&2
    else
        fail "$test_name: expected exit code $expected_exit_code, got $EXIT_CODE"
        if [ -f feedback/judgemessage.txt ]; then
            cat feedback/judgemessage.txt >&2
        fi
    fi

    # Clean up
    rm -rf judge_in.txt judge_ans.txt team_out.txt feedback
}

test_identical_files() {
    exec_compare 42 "Identical files" "hello world" "hello world"
}

test_different_files() {
    exec_compare 43 "Different files" "hello world" "hello there"
}

test_float_within_tolerance() {
    exec_compare 42 "Float comparison, within tolerance" "1.000000000" "1.000000001" float_tolerance 1.1e-9
}

test_float_outside_tolerance() {
    exec_compare 43 "Float comparison, outside tolerance" "1.000" "1.001" float_tolerance 1e-4
}

test_invalid_float() {
    exec_compare 43 "Invalid float (should fail with current isfloat)" "1.0" "1.0a" float_tolerance 1e-9
}

test_case_insensitive_pass() {
    exec_compare 42 "Case-insensitive comparison (pass)" "Hello World" "hello world"
}

test_case_sensitive_fail() {
    exec_compare 43 "Case-sensitive comparison (fail)" "Hello World" "hello world" case_sensitive
}

test_space_change_sensitive_pass() {
    exec_compare 42 "Space change comparison (pass)" "hello world" "hello  world"
}

test_space_change_sensitive_fail() {
    exec_compare 43 "Space change sensitive comparison (fail)" "hello world" "hello  world" space_change_sensitive
}

test_invalid_float_extra_chars() {
    exec_compare 43 "Invalid float with extra characters" "1.0" "1.0abc" float_tolerance 1e-9
}


any_test_failed=0

g++ -g -O2 -Wall -std=c++20 ../sql/files/defaultdata/compare/compare.cc -o $COMPARE_EXECUTABLE

for func in $(compgen -o nosort -A function test_); do
    fail=0
    $func
    if [ $fail -ne 0 ]; then
        any_test_failed=1
    fi
done

rm -f $COMPARE_EXECUTABLE

exit $any_test_failed
