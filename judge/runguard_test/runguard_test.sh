#!/bin/bash

cd "$(dirname "${BASH_SOURCE}")"

RUNGUARD=../runguard
LOG1="$(mktemp)"
LOG2="$(mktemp)"
META=$(mktemp -p "$judgehost_tmpdir")

fail() {
	fail=1
	msg="$1"
	echo -e "\e[31mFAIL $msg\e[0m" >&2
	return 1
}

expect_file() {
	file=$1
	token="$2"
	grep -q "$token" "$file" || fail "did not find expected '$token' in '$file', first few lines: $(head -n20 "$file")"
}

expect_meta() {
	expect_file "$META" "$1"
}

expect_stdout() {
	expect_file "$LOG1" "$1"
}

expect_stderr() {
	expect_file "$LOG2" "$1"
}

not_expect_stdout() {
	token="$1"
	grep -q "$token" "$LOG1" && fail "did find unexpected '$token' in log, first few lines: $(head "$LOG1")"
}

exec_check_success() {
	all_args_string=$(printf "%s " "$@")
	"$@" > "$LOG1" 2> "$LOG2" || fail "expected command ('$all_args_string') to succeed, first few lines of stderr: $(head "$LOG2")"
}

exec_check_fail() {
	all_args_string=$(printf "%s " "$@")
	"$@" > "$LOG1" 2> "$LOG2" && fail "expected command ('$all_args_string') to fail"
}

test_no_command() {
	exec_check_fail $RUNGUARD
	expect_stderr "no command specified"
}

test_no_sudo() {
	exec_check_fail $RUNGUARD ls
	expect_stderr "creating cgroup"
}

test_no_sudo() {
	exec_check_fail sudo $RUNGUARD ls
	expect_stderr "root privileges not dropped"
}

test_ls() {
	exec_check_success sudo $RUNGUARD -u domjudge-run-0 ls
	expect_stdout "runguard_test.sh"
}

test_walltime_limit() {
	exec_check_success sudo $RUNGUARD -u domjudge-run-0 -t 2 sleep 1

	exec_check_fail sudo $RUNGUARD -u domjudge-run-0 -t 2 sleep 3
	expect_stderr "timelimit exceeded"
	expect_stderr "hard wall time"
}

test_cputime_limit() {
	# 2 threads, ~3s of CPU time, gives ~1.5s of wall time.
	exec_check_success sudo $RUNGUARD -u domjudge-run-0 -C 3.1 ./threads 2 3

	# Now also limiting wall time to 2s.
	exec_check_success sudo $RUNGUARD -u domjudge-run-0 -C 3.1 -t 2 ./threads 2 3

	# Some failing cases.
	exec_check_fail sudo $RUNGUARD -u domjudge-run-0 -C 2.9 ./threads 2 3
	exec_check_fail sudo $RUNGUARD -u domjudge-run-0 -C 3.1 -t 1.4 ./threads 2 3
}

test_cputime_pinning() {
	# 2 threads, ~3s of CPU time, with one core we are out of luck...
	exec_check_fail sudo $RUNGUARD -u domjudge-run-0 -C 3.1 -t 2 -P 1 ./threads 2 3
	# ...but with two cores it works.
	exec_check_success sudo $RUNGUARD -u domjudge-run-0 -C 3.1 -t 2 -P 0-1 ./threads 2 3
}

test_streamsize() {
	exec_check_fail sudo $RUNGUARD -u domjudge-run-0 -t 1 -s 123 yes DOMjudge
	expect_stdout "DOMjudge"
	limit=$((123*1024))
	actual=$(cat "$LOG1" | wc -c)
	[ $limit -eq $actual ] || fail "stdout not limited to ${limit}B, but wrote ${actual}B"
}

test_streamsize_stderr() {
	exec_check_fail sudo $RUNGUARD -u domjudge-run-0 -t 1 -s 42 ./fill-stderr.sh
	expect_stderr "DOMjudge"
	# Allow 100 bytes extra, for the runguard time limit message.
	limit=$((42*1024 + 100))
	actual=$(cat "$LOG2" | wc -c)
	[ $limit -gt $actual ] || fail "stdout not limited to ${limit}B, but wrote ${actual}B"
}

test_redir_stdout() {
	stdout=$(mktemp -p "$judgehost_tmpdir")
	chmod go+rwx "$stdout"

	# Basic test.
	exec_check_success sudo $RUNGUARD -u domjudge-run-0 -o "$stdout" echo 'foobar'
	grep -q "foobar" "$stdout" || fail "did not find expected 'foobar' in redirect stdout"
	
	# Verify that stdout is empty.
	actual=$(cat "$LOG1" | wc -c)
	[ $actual -eq 0 ] || fail "stdout should be empty, but contains ${actual}B"

	# This will fail because of the timeout.
	exec_check_fail sudo $RUNGUARD -u domjudge-run-0 -t 1 -s 23 -o "$stdout" yes DOMjudge
	expect_stderr "timelimit exceeded"
	expect_stderr "hard wall time"

	# Verify that stdout is empty.
	actual=$(cat "$LOG1" | wc -c)
	[ $actual -eq 0 ] || fail "stdout should be empty, but contains ${actual}B"

	# Verify that redirected stdout has the right contents.
	grep -q "DOMjudge" "$stdout" || fail "did not find expected 'DOMjudge' in redirect stdout"
	limit=$((23*1024))
	actual=$(cat "$stdout" | wc -c)
	[ $limit -eq $actual ] || fail "redirected stdout not limited to ${limit}B, but wrote ${actual}B"

	rm "$stdout"
}

test_redir_stderr() {
	stderr=$(mktemp -p "$judgehost_tmpdir")
	chmod go+rwx "$stderr"

	# This will fail because of the timeout.
	exec_check_fail sudo $RUNGUARD -u domjudge-run-0 -t 1 -s 11 -e "$stderr" ./fill-stderr.sh
	expect_stderr "timelimit exceeded"
	expect_stderr "hard wall time"

	# Verify that actual stderr does not contain DOMjudge.
	grep -q "DOMjudge" "$LOG2" && fail "did find unexpected 'DOMjudge' in stderr"

	# Verify that redirected stdout has the right contents.
	grep -q "DOMjudge" "$stderr" || fail "did not find expected 'DOMjudge' in redirect stderr"
	limit=$((11*1024))
	actual=$(cat "$stderr" | wc -c)
	[ $limit -eq $actual ] || fail "redirected stdout not limited to ${limit}B, but wrote ${actual}B"

	rm "$stderr"
}

test_rootdir_changedir() {
	# Prepare test directory.
	almost_empty_dir="$judgehost_judgedir/runguard_tests/almost_empty"
	mkdir -p "$almost_empty_dir"/exists
	cp hello "$almost_empty_dir"/
	ln -sf /hello "$almost_empty_dir"/exists/foo

	exec_check_success sudo $RUNGUARD -u domjudge-run-0 -r "$almost_empty_dir" ./hello
	expect_stdout "Hello DOMjudge"

	exec_check_fail sudo $RUNGUARD -u domjudge-run-0 -r "$almost_empty_dir" -d doesnotexist /hello
	expect_stderr "cannot chdir to \`doesnotexist' in chroot"

	exec_check_success sudo $RUNGUARD -u domjudge-run-0 -r "$almost_empty_dir" -d exists /hello
	expect_stdout "Hello DOMjudge"

	exec_check_success sudo $RUNGUARD -u domjudge-run-0 -r "$almost_empty_dir" -d exists ./foo
	expect_stdout "Hello DOMjudge"

	exec_check_success sudo $RUNGUARD -u domjudge-run-0 -r "$almost_empty_dir" -d exists /exists/foo
	expect_stdout "Hello DOMjudge"
}

test_memsize() {
	# This is slightly over the limit as there is other stuff to be allocated as well.
	exec_check_fail sudo $RUNGUARD -u domjudge-run-0 -m 1024 ./mem $((1024*1024))

	exec_check_success sudo $RUNGUARD -u domjudge-run-0 -m 1500 ./mem $((1024*1024))
	expect_stdout "mem = 1048576"

	exec_check_fail sudo $RUNGUARD -u domjudge-run-0 -m $((1024*1024)) ./mem $((1024*1024*1024))
	exec_check_success sudo $RUNGUARD -u domjudge-run-0 -m $((1024*1024 + 10000)) ./mem $((1024*1024*1024))
	expect_stdout "mem = 1073741824"
}

test_envvars() {
	exec_check_success sudo $RUNGUARD -u domjudge-run-0 ./print_envvars.py
	expect_stdout "COUNT: 2."
	expect_stdout "PATH="
	expect_stdout "LC_CTYPE="
	not_expect_stdout "DOMjudge"

	exec_check_success sudo $RUNGUARD -u domjudge-run-0 -E ./print_envvars.py
	expect_stdout "HOME="
	expect_stdout "USER="
	expect_stdout "SHELL="

	exec_check_success sudo $RUNGUARD -u domjudge-run-0 -V"DOMjudgeA=A;DOMjudgeB=BB" ./print_envvars.py
	expect_stdout "COUNT: 4."
	expect_stdout "DOMjudgeA=A"
	expect_stdout "DOMjudgeB=BB"
	not_expect_stdout "HOME="
	not_expect_stdout "USER="
	not_expect_stdout "SHELL="
}

test_nprocs() {
	exec_check_success sudo $RUNGUARD -u domjudge-run-0 ./forky.sh
	expect_stdout 31

	exec_check_fail sudo $RUNGUARD -u domjudge-run-0 -p 16 ./forky.sh
	if [ -n "$GITHUB_ACTIONS" ]; then
		# TODO: Why does this not output anything on github, perhaps we
		# need to use a different user id for domjudge-run-0
		ps axuwww | grep ^domjudge-run
	else
		expect_stdout 15
	fi
	not_expect_stdout 16
	not_expect_stdout 31
	expect_stderr "Resource temporarily unavailable"
}

test_meta() {
	exec_check_success sudo $RUNGUARD -u domjudge-run-0 -t 2 -M "$META" sleep 1
	expect_meta 'wall-time: 1.0'
	expect_meta 'cpu-time: 0.0'
	expect_meta 'sys-time: 0.0'
	expect_meta 'time-used: wall-time'
	expect_meta 'exitcode: 0'
	expect_meta 'stdin-bytes: 0'
	expect_meta 'stdout-bytes: 0'
	expect_meta 'stderr-bytes: 0'

	exec_check_fail sudo $RUNGUARD -u domjudge-run-0 -M "$META" false
	expect_meta 'exitcode: 1'

	echo "DOMjudge" | sudo $RUNGUARD -u domjudge-run-0 -t 2 -M "$META" rev > "$LOG1" 2> "$LOG2"
	expect_meta 'wall-time: 0.0'
	expect_meta 'stdout-bytes: 9'
	expect_stdout "egdujMOD"

	exec_check_fail sudo $RUNGUARD -u domjudge-run-0 -C 3.1 -t 1.4 -M "$META" ./threads 2 3
	expect_meta 'exitcode: 143'
	expect_meta 'signal: 14'
	expect_meta 'wall-time: 1.5'
	expect_meta 'time-result: hard-timelimit'

	exec_check_success sudo $RUNGUARD -u domjudge-run-0 -C 1:5 -M "$META" ./threads 2 3
	expect_meta 'time-used: cpu-time'
	expect_meta 'time-result: soft-timelimit'
	expect_meta 'exitcode: 0'

	exec_check_fail sudo $RUNGUARD -u domjudge-run-0 -t 1 -s 3 -M "$META" ./fill-stderr.sh
	# We expect stderr-bytes to have a non-zero value.
	expect_meta 'stderr-bytes: '
	grep -q 'stderr-bytes: 0' "$META" && fail ""
	expect_meta 'output-truncated: stderr'
}

any_test_failed=0
only_func=$1
for func in $(compgen -o nosort -A function test_); do
	# Check whether the user requested to run a single function.
	if [[ -n "$only_func" && "$only_func" != "$func" ]]; then
		continue;
	fi
	fail=0
	echo -n "- $func "
	$func
	if [ $fail -eq 0 ]; then
		echo -e "\e[32m✔\e[0m" >&2
	else
		echo -e "\e[31m✘\e[0m" >&2
		any_test_failed=1
	fi
done

rm -f "$LOG1" "$LOG2" "$META"

exit $any_test_failed
