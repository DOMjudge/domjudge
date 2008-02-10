#!/bin/sh
# $Id$
# Generate apache.conf from apache.template.conf

. config.sh

WEBSUBDIR=`echo "$WEBBASEURI" | sed "s!^.*$WEBSERVER[^/]*/\(.*\)/!\1!"`

eval echo "\"`cat apache.template.conf`\"" > apache.conf
