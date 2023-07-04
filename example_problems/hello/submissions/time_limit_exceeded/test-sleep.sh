#!/bin/sh

# This only outputs the correct answer when we cleanup
# so it should be too-late to be considered correct.

cleanup() {
    echo "Hello world!"
}

trap cleanup INT ABRT TERM TSTP QUIT

while true; do
    sleep 5
done