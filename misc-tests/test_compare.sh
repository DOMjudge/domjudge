#!/bin/bash
# shellcheck disable=SC2317

set -e

COMPARE_EXECUTABLE=./compare_test_executable

fail() {
    fail=1
    msg="$1"
    echo -e "\e[31mFAIL: $msg\e[0m" >&2
    # Not returning an error here, `set -e` would stop at the first failure.
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


# The tests below are table-driven. compare_group takes a label, the judge
# answer and the compare options, and reads one case per line from stdin:
#
#   <ac|wa> <team output>
#
# ac means the team output must be accepted (exit code 42), wa that it must be
# rejected (exit code 43). Judge answer and team output may contain \n, \t
# and \x20 (see printf %b).
#
# Cases under "Current behavior" pin down behavior that is probably unwanted
# and should be changed in the future. Update them together with the change.

# Runs compare on one case and stores the exit code in EXIT_CODE.
run_compare_raw() {
    local ans=$1 team=$2
    shift 2
    printf 'does not matter\n' > judge_in.txt
    printf '%b' "$ans" > judge_ans.txt
    printf '%b' "$team" > team_out.txt
    mkdir -p feedback
    EXIT_CODE=0
    # The outer braces hide bash's "Aborted" message for judge errors.
    { $COMPARE_EXECUTABLE judge_in.txt judge_ans.txt feedback "$@" < team_out.txt >/dev/null 2>&1 || EXIT_CODE=$?; } 2>/dev/null
    rm -rf judge_in.txt judge_ans.txt team_out.txt feedback
}

report_group() {
    echo -e "- $1: $2 cases \e[32m✔\e[0m"
}

compare_group() {
    local label=$1 ans=$2
    shift 2
    local line kind team expected total=0
    while IFS= read -r line; do
        [ -z "$line" ] && continue
        # Split at the first space only, the team output is taken verbatim.
        kind=${line%% *}
        if [[ $line == *' '* ]]; then team=${line#* }; else team=''; fi
        total=$((total + 1))
        case $kind in
            ac) expected=42 ;;
            wa) expected=43 ;;
            *) fail "$label: unknown case kind '$kind'"; continue ;;
        esac
        run_compare_raw "$ans" "$team" "$@"
        if [ "$EXIT_CODE" -ne "$expected" ]; then
            fail "$label [$*]: team output '$team': expected exit code $expected, got $EXIT_CODE"
        fi
    done
    report_group "$label" "$total"
}

# Invalid options must give a judge error, which is neither AC nor WA.
compare_usage_group() {
    local label=$1 opts total=0
    while IFS= read -r opts; do
        [ -z "$opts" ] && continue
        total=$((total + 1))
        # shellcheck disable=SC2086
        run_compare_raw "1" "1" $opts
        if [ "$EXIT_CODE" -eq 42 ] || [ "$EXIT_CODE" -eq 43 ]; then
            fail "$label: '$opts' should be a judge error, got exit code $EXIT_CODE"
        fi
    done
    report_group "$label" "$total"
}

# The cases are inspired by the default output validator tests of BAPCtools:
# https://github.com/RagnarGrootKoerkamp/BAPCtools/blob/main/test/default_output_validator/default_output_validator.yaml

test_group_space() {
    compare_group "Space lenient" 'A B' <<'EOF'
ac A B
ac  A \t B\n
ac A\n\n B
wa AB
wa A B C
wa C A B
EOF
    compare_group "Space lenient, empty answer" '' <<'EOF'
ac
ac \n\t \n
wa 0
EOF
    compare_group "Space sensitive" 'A B\n' space_change_sensitive <<'EOF'
ac A B\n
wa A  B\n
wa A\tB\n
wa A B
wa  A B\n
EOF
    compare_group "Space sensitive, empty answer" '' space_change_sensitive <<'EOF'
ac
wa \n
wa \x20
EOF
}

test_group_case() {
    compare_group "Case insensitive" 'A B' <<'EOF'
ac a b
ac A b
wa A C
wa B A
EOF
    compare_group "Case sensitive" 'A B' case_sensitive <<'EOF'
ac A B
wa a b
wa A b
EOF
}

test_group_float_tolerance() {
    compare_group "Absolute tolerance" '10 11' float_absolute_tolerance 1 <<'EOF'
ac 10 11
ac 9 12
ac 1E1 1.1E1
wa 10 9.99999999
wa 11.000000001 11
EOF
    compare_group "Relative tolerance" '10 20' float_relative_tolerance 0.5 <<'EOF'
ac 5 30
wa 4.99999 20
wa 10 30.00001
wa 10 asdf
EOF
    compare_group "Absolute and relative tolerance" '1000 0.001' float_tolerance 0.5 <<'EOF'
ac 500 -0.499
ac 1500 0.501
wa 499 0.001
wa 1000 0.5010001
wa 1000 0.001 X
EOF
    compare_group "Zero tolerance" '1.5 2' float_tolerance 0 <<'EOF'
ac 1.50 2.0
ac 15e-1 +2
wa 1.5000001 2
EOF
}

test_group_float_or_string() {
    # Only tokens that are completely a number are compared as numbers.
    compare_group "Number and word" 'A 1000' float_tolerance 0.5 <<'EOF'
ac a 1001
wa A 999a
wa 1000 A
EOF
    compare_group "Number followed by a word" '1000A' float_tolerance 0.5 <<'EOF'
ac 1000a
wa 1000.0a
wa 1000
EOF
    # Without tolerance every token is a string, even if it looks like a number.
    compare_group "No tolerance" '0' <<'EOF'
ac 0
wa 0.0
wa -0
wa 0e0
wa 00
EOF
}

test_group_float_syntax() {
    compare_group "Spellings of zero" '0' float_tolerance 0 <<'EOF'
ac 0
ac +0
ac -0.0
ac 00
ac .0
ac 0.
ac 0e1
ac 0E-01
ac .0e+1
ac -00.e1
EOF
    compare_group "Spellings of 100" '100' float_tolerance 0 <<'EOF'
ac 100.0
ac 1e2
ac 1.E+02
ac .1e3
ac 0.100000000000000000000000000000000000000000e+00000000000000000000000000000000000000003
wa 1e3
wa 100x
EOF
    compare_group "Malformed numbers" '0' float_tolerance 0 <<'EOF'
wa .
wa -.
wa ..
wa ++0
wa +-0
wa .e1
wa 0e
wa 0e+
wa 0e1.0
wa 1,0
wa 1.0.0
wa 1.0abcdefghijklmnop
EOF
}

test_group_float_current_behavior() {
    # Current behavior: sscanf parses hexadecimal floats, infinity and nan.
    # They should be plain words, so all "ac" cases below should be "wa".
    compare_group "Hexadecimal floats" '0' float_tolerance 0 <<'EOF'
ac 0x0
ac 0X0p0
EOF
    compare_group "Spellings of inf" 'inf' float_tolerance 0.5 <<'EOF'
ac infinity
wa -inf
wa nan
EOF
    compare_group "Spellings of nan" 'nan' float_tolerance 0.5 <<'EOF'
ac NaN
ac -nan
wa inf
EOF
    compare_group "inf, case sensitive" 'inf' float_tolerance 0.5 case_sensitive <<'EOF'
ac inf
ac INF
EOF
    # Current behavior: numbers outside of the long double range become
    # infinity or zero, so different huge numbers compare equal.
    compare_group "Out of range" '1e5000' float_tolerance 0 <<'EOF'
ac 1e5000
ac 1e5001
ac inf
EOF
    compare_group "Out of range, tiny" '0' float_tolerance 0 <<'EOF'
wa 1e-4000
ac 1e-20000
EOF
}

test_group_usage() {
    compare_usage_group "Invalid options" <<'EOF'
unknown_option
float_tolerance
float_tolerance abc
float_tolerance 1e-5x
float_absolute_tolerance 1e
EOF
}

test_missing_arguments() {
    echo -n "- Missing arguments "
    EXIT_CODE=0
    { $COMPARE_EXECUTABLE judge_in.txt >/dev/null 2>&1 </dev/null || EXIT_CODE=$?; } 2>/dev/null
    if [ "$EXIT_CODE" -ne 42 ] && [ "$EXIT_CODE" -ne 43 ]; then
        echo -e "\e[32m✔\e[0m" >&2
    else
        fail "Missing arguments: should be a judge error, got exit code $EXIT_CODE"
    fi
}

test_group_trailing_output() {
    compare_group "Missing and trailing output" '1 2 3' <<'EOF'
ac 1 2 3
wa 1 2
wa
wa 1 2 3 4
EOF
}

any_test_failed=0

# Set COMPARE_SOURCE to test a different compare.cc.
COMPARE_SOURCE=${COMPARE_SOURCE:-../sql/files/defaultdata/compare/compare.cc}
g++ -g -O2 -Wall -std=c++20 "$COMPARE_SOURCE" -o $COMPARE_EXECUTABLE

for func in $(compgen -o nosort -A function test_); do
    fail=0
    $func
    if [ $fail -ne 0 ]; then
        any_test_failed=1
    fi
done

rm -f $COMPARE_EXECUTABLE

exit $any_test_failed
