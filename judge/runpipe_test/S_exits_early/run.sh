#!/usr/bin/env bash

[[ $# != 1 ]] && echo "Usage: $0 runpipe" && exit 2

source ../check.sh
should_exit_with 42 "$1" ./judge 42 = ./solution 42
should_exit_with 42 "$1" -o output.txt ./judge 42 = ./solution 42
