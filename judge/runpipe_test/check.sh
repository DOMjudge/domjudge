#!/usr/bin/env bash

function should_timeout() {
  timeout -s TERM 1s "$@"
  ret=$?
  if [[ "$ret" != 124 ]]; then
    printf "\033[31;1mExpecting timeout, got %s\033[0m\n" $ret
    echo "   " "$@"
    exit 1
  else
    printf "\033[32;1mok\033[0m\n"
  fi
}

function should_exit_with() {
  code="$1"; shift
  timeout 1s "$@"
  ret=$?
  if [[ "$ret" == 124 ]]; then
    printf "\033[31;1mTimeout not expected\033[0m\n"
    echo "   " "$@"
    exit 1
  elif [[ "$ret" != "$code" ]]; then
    printf "\033[31;1mExpecting code %s, got %s\033[0m\n" "$code" "$ret"
    echo "   " "$@"
    exit 1
  else
    printf "\033[32;1mok\033[0m\n"
  fi
}

function should_not_busy_wait() {
  "$@" &
  pid=$!
  sleep 0.5
  # The CPU time (user + system) used so far by runpipe, in clock ticks.
  ticks=$(awk '{print $14 + $15}' "/proc/$pid/stat")
  kill -TERM "$pid"
  wait "$pid"
  max_ticks=$(( $(getconf CLK_TCK) / 10 ))
  if [[ "$ticks" -gt "$max_ticks" ]]; then
    printf "\033[31;1mExpecting at most %s ticks of CPU time, got %s\033[0m\n" "$max_ticks" "$ticks"
    echo "   " "$@"
    exit 1
  else
    printf "\033[32;1mok\033[0m\n"
  fi
}
