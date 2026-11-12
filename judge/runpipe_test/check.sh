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
  # The command must still be running, otherwise there is nothing to measure.
  # Strip everything up to the command name, which may contain spaces.
  stat=$(cat "/proc/$pid/stat" 2>/dev/null)
  stat=${stat##*) }
  state=$(echo "$stat" | awk '{print $1}')
  # The CPU time (user + system) used so far by runpipe, in clock ticks.
  ticks=$(echo "$stat" | awk '{print $12 + $13}')
  kill -TERM "$pid" 2>/dev/null
  wait "$pid"
  max_ticks=$(( $(getconf CLK_TCK) / 10 ))
  if [[ -z "$state" || "$state" == Z || ! "$ticks" =~ ^[0-9]+$ ]]; then
    printf "\033[31;1mExpecting the command to keep running, but it exited\033[0m\n"
    echo "   " "$@"
    exit 1
  elif [[ "$ticks" -gt "$max_ticks" ]]; then
    printf "\033[31;1mExpecting at most %s ticks of CPU time, got %s\033[0m\n" "$max_ticks" "$ticks"
    echo "   " "$@"
    exit 1
  else
    printf "\033[32;1mok\033[0m\n"
  fi
}
