#!/bin/bash
#
# Wrapper script for the submit client binary to implement the
# commandline switches as outlined in the CCS specification.
# Note that all options must be passed before any submission
# filenames.

# The environment variable below can be set to override compiled in
# defaults in the submit binary:

#export SUBMITBASEURL="http://localhost/domjudge/"

PASSOPTS=''
MAINSOURCE=''
ARGV=($@)
while getopts ':u:w:m:t:' OPT ; do
	case "$OPT" in
		u) PASSOPTS="$PASSOPTS -t $OPTARG" ;;
		w) PASSOPTS="$PASSOPTS -x $OPTARG" ;;
		m) MAINSOURCE="$OPTARG" ;;
		t) PASSOPTS="$PASSOPTS -T $OPTARG" ;;
# Pass unknown option unmodified to the submit client:
		?)
			# Hacky check for possible option argument
			PASSOPTS="$PASSOPTS -$OPTARG"
			ARG="${ARGV[$((OPTIND-1))]}"
			if [ -n "$ARG" -a "${ARG#-}" = "$ARG" ]; then
				PASSOPTS="$PASSOPTS $ARG"
				((OPTIND++))
			fi
			;;
		:)
			echo "Error: option '$OPTARG' requires an argument."
			exit 1
			;;
		*)
			echo "Error: unknown error reading option '$OPT', value '$OPTARG'."
			exit 1
			;;
	esac
done
shift $((OPTIND-1))

# Webinterface is required for password authentication:
PASSOPTS="$PASSOPTS -w1"

exec ./submit $PASSOPTS $MAINSOURCE "$@"
