#!/usr/bin/env bash

[[ $# != 1 ]] && echo "Usage: $0 runpipe" && exit 2

source ../check.sh

should_timeout "$1" ./judge 42 = ./solution 42
should_timeout "$1" -o output.txt ./judge 42 = ./solution 42
