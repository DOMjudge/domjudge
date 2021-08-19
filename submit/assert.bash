# Inspired by https://github.com/ztombol/bats-assert.

fail()
{
    local -r expected="$1"
    { 
        echo "Expected: '$expected'"
	echo "Output:"
	for (( idx = 0; idx < ${#lines[@]}; ++idx )); do
		echo "${lines[$idx]}"
	done
    } >&2
    return 1
}

assert_line() {
    local -r expected="$1"
    for (( idx = 0; idx < ${#lines[@]}; ++idx )); do
        if [[ ${lines[$idx]} == "$expected" ]]; then
	    return 0
	fi
    done
    fail "$expected"
}

refute_line() {
    local -r unexpected="$1"
    for (( idx = 0; idx < ${#lines[@]}; ++idx )); do
        if [[ ${lines[$idx]} == "$unexpected" ]]; then
	    echo "Unexpected string '$unexpected' found."
	    return 1
	fi
    done
    return 0
}

assert_regex() {
    local -r expected="$1"
    for (( idx = 0; idx < ${#lines[@]}; ++idx )); do
        if [[ ${lines[$idx]} =~ $expected ]]; then
	    return 0
	fi
    done
    fail "$expected"
}

assert_partial() {
    local -r expected="$1"
    for (( idx = 0; idx < ${#lines[@]}; ++idx )); do
        if [[ ${lines[$idx]} == *"$expected"* ]]; then
	    return 0
	fi
    done
    fail "$expected"
}

assert_success() {
    if (( status != 0 )); then
	echo "Expected success, status=$status."
        return 1
    fi
    return 0
}

assert_failure() {
    local -r expected_exit_code="$1"
    if (( status != "$expected_exit_code" )); then
	echo "Expected failure with exit code = $expected_exit_code, status=$status."
        return 1
    fi
    return 0
}
