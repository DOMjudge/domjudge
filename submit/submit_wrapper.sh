#!/bin/sh
#
# Wrapper script for the submit client binary to pass submit server
# address, url and possibly other information.
#
# Use it e.g. by renaming the 'submit' client binary to 'submit-main'
# and install this script as 'submit' on the teams' workstations.
# Replace the SUBMITSERVER and SUBMITBASEURL variables your local
# values and modify the last list to execute the correct main submit
# program.

SUBMITSERVER="localhost"
SUBMITBASEURL="http://localhost/domjudge/"

export SUBMITSERVER SUBMITBASEURL

exec submit "$@"
