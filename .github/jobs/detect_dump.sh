#!/bin/bash

set -euxo pipefail

OUT=$(find ./ -name ".git*" -type d -prune -o \
              -name "lib" -prune -o \
              -name "var" -prune -o \
              -name "bundles" -prune -o \
              -name "cache" -type d -prune -o \
              -name "ace" -type d -prune -o \
              -type f -print0 | xargs -0 grep --color "dump(" | grep -v "Yaml::dump(") || true

# Show detected debug statements
echo "$OUT" >&2

# Fail and terminate if we found any statements
if [ -n "$OUT" ]; then
    exit 1
fi
