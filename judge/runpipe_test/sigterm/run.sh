#!/usr/bin/env bash

# Start runpipe and after a while send SIGTERM. The two processes are waiting
# to receive it and will create two files (judge.txt and solution.txt).

[[ $# != 1 ]] && echo "Usage: $0 runpipe" && exit 2

source ../check.sh

function check_sigterm() {
  if [[ ! -f judge.txt ]]; then
    printf "\033[31;1mJudge didn't receive SIGTERM\033[0m\n"
    exit 1
  fi
  if [[ ! -f solution.txt ]]; then
    printf "\033[31;1mSolution didn't receive SIGTERM\033[0m\n"
    exit 1
  fi
  printf "\033[32;1mok\033[0m\n"
}

function run_test() {
  rm -f judge.txt solution.txt

  # Start the process and send SIGTERM after a while
  "$@" &
  pid=$!
  sleep 0.5s
  kill -TERM $pid
  wait $pid

  check_sigterm
}

run_test "$1" ./judge 42 = ./solution 42
run_test "$1" -o output.txt ./judge 42 = ./solution 42
