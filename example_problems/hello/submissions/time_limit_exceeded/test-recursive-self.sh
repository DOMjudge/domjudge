#!/bin/bash

# See: https://codegolf.stackexchange.com/a/24488
# This keeps on calling itself so runs forever.

:(){ : $@$@;};: :