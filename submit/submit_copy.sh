#!/bin/bash
# $Id$

# Script to copy a submission file from a team to judge account.
# Usage: $0 <team> <fromfile> <tofile>

[ $# -eq 3 ] || exit 1

team=$1
fromfile=$2
tofile=$3

#output=`scp -Bq "$team@localhost:$fromfile" "$tofile" 2>&1`
#[ $? -eq 0 -a ${#output} -eq 0 ] || exit 1

cp "$fromfile" "$tofile" || exit 1

exit 0








