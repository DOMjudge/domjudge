#!/usr/bin/env bash

[[ $# != 1 ]] && echo "Usage: $0 runpipe" && exit 2

source ../check.sh
should_timeout "$1" ./judge input.txt output.txt out = ./solution 42
should_timeout "$1" -o /dev/null ./judge input.txt output.txt out = ./solution 42
