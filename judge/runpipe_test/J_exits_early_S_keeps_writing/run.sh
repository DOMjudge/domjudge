#!/usr/bin/env bash

[[ $# != 1 ]] && echo "Usage: $0 runpipe" && exit 2

source ../check.sh
should_not_busy_wait "$1" ./judge = ./solution
should_not_busy_wait "$1" -o output.txt ./judge = ./solution
