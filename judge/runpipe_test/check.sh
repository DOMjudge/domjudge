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
