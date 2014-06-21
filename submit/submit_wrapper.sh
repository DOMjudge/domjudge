#!/bin/sh
#
# Wrapper script for the submit client binary to pass submit url
# and possibly other information.
#
# Use it e.g. by renaming the 'submit' client binary to 'submit-main'
# and install this script as 'submit' on the teams' workstations.
# Replace the SUBMITBASEURL variable's value with your local
# value and modify the last list to execute the correct main submit
# program.

SUBMITBASEURL="http://localhost/domjudge/"

export SUBMITBASEURL

exec submit "$@"
