#!/usr/bin/env bash

[[ $# != 1 ]] && echo "Usage: $0 runpipe" && exit 2

source ../check.sh
should_exit_with 42 "$1" ./judge = ./solution
rm -f output.txt meta.txt
should_exit_with 42 "$1" -o output.txt -M meta.txt ./judge = ./solution

# The judge writes 16 MiB, but the solution reads only a few KiB of it. What
# could not be delivered must not end up in the capture or the byte count.
max_bytes=$((1024 * 1024))
bytes=$(sed -n 's/^bytes-transferred: //p' meta.txt)
size=$(stat -c %s output.txt)
if [[ -z "$bytes" || "$bytes" -ge "$max_bytes" || "$size" -ge "$max_bytes" ]]; then
  printf "\033[31;1mExpecting less than %s bytes, got %s transferred and %s captured\033[0m\n" "$max_bytes" "$bytes" "$size"
  exit 1
else
  printf "\033[32;1mok\033[0m\n"
fi
