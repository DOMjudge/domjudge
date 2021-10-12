#!/bin/bash
set -eux -o pipefail

# Checks whether a Contest API conforms to the specification
# https://ccs-specs.icpc.io/contest_api

# Set path to json-validate binary if it's not in PATH:
#VALIDATE_JSON=/path/to/validate-json

ENDPOINTS='
contests
judgement-types
languages
problems
groups
organizations
team-members
teams
state
submissions
judgements
runs
clarifications
awards
scoreboard
'

# Note: event-feed is an NDJSON endpoint which is treated specially.

ENDPOINTS_OPTIONAL='
team-members
awards
'

ENDPOINTS_TO_FAIL='
404:doesnt-exist
404:doesnt-exist/42
404:submissions/999999
404:submissions/xyz9999
404:submissions/XYZ_999
404:submissions/XYZ-999
400:event-feed?since_id=999999
400:event-feed?since_id=xY-99_
'

# We later re-add optional endpoints to ENDPOINTS_CHECK_CONSISTENT if
# they were actually found.
ENDPOINTS_CHECK_CONSISTENT="$ENDPOINTS"
for endpoint in $ENDPOINTS_OPTIONAL scoreboard ; do
	ENDPOINTS_CHECK_CONSISTENT="${ENDPOINTS_CHECK_CONSISTENT/$endpoint/}"
done

error()
{
	echo "Error: $*"
	exit 1
}

verbose()
{
	if [ -z "$QUIET" ]; then
		if [ $# -eq 1 ]; then
			echo "$1"
		else
			printf "$@"
		fi
	fi
}

usage()
{
	cat <<EOF
$(basename $0) - Validate a Contest API implementation with JSON schema.

Usage: $(basename $0) [option]... URL

This program validates a Contest API implementation against the
specification: https://ccs-specs.icpc.io/contest_api

The URL must point to the base of the API, for example:

  $(basename $0) -n -c '-knS' -a 'strict=1' https://example.com/api

where the options -knS passed to curl make it ignore SSL certificate
errors, use ~/.netrc for credentials, and be verbose. The option -a
makes that 'strict=1' is appended as argument to each API call.

This script requires:
- the curl command line client
- the validate-json binary from https://github.com/justinrainbow/json-schema
  which can be installed with \`composer require justinrainbow/json-schema\`
- the jq program from https://github.com/stedolan/jq
  which is available as the \`jq\` package in Debian and Ubuntu.

Options:

  -a ARGS  Arguments to pass to the API request URLs. Separate arguments
             with '&', do not add initial '?'. (default: $URL_ARGS)
  -C       Check internal consistency between REST endpoints and event feed.
  -c OPTS  Options to pass to curl to request API data (default: $CURL_OPTIONS)
  -d       Turn on shell script debugging.
  -e       Check correct HTTP error codes for non-existent endpoints.
  -h       Snow this help output.
  -j PROG  Specify the path to the 'validate-json' binary.
  -n       Require that all collection endpoints are non-empty.
  -p       Allow extra properties beyond those defined in the Contest API.
  -t TIME  Timeout in seconds for downloading event feed (default: $FEED_TIMEOUT)
  -q       Quiet mode: suppress all output except script errors.

The script reports endpoints checked and validations errors.
In quiet mode only the exit code indicates successful validation.

EOF
}

FEED_TIMEOUT=10
CURL_OPTIONS='-n -s'
URL_ARGS=''

# Parse command-line options:
while getopts 'a:Cc:dehj:npt:q' OPT ; do
	case "$OPT" in
		a) URL_ARGS="$OPTARG" ;;
		C) CHECK_CONSISTENCY=1 ;;
		c) CURL_OPTIONS="$OPTARG" ;;
		d) export DEBUG=1 ;;
		e) CHECK_ERRORS=1 ;;
		h) usage ; exit 0 ;;
		j) VALIDATE_JSON="$OPTARG" ;;
		n) NONEMPTY=1 ;;
		p) EXTRAPROP=1 ;;
		t) FEED_TIMEOUT="$OPTARG" ;;
		q) QUIET=1 ;;
		:)
			error "option '$OPTARG' requires an argument."
			exit 1
			;;
		?)
			error "unknown option '$OPTARG'."
			exit 1
			;;
		*)
			error "unknown error reading option '$OPT', value '$OPTARG'."
			exit 1
			;;
	esac
done
shift $((OPTIND-1))

[ -n "$DEBUG" ] && set -x

API_URL="$1"

if [ -z "$API_URL" ]; then
	error "API URL argument expected."
	exit 1
fi

TMP=$(mktemp -d)

MYDIR=$(dirname $0)

query_endpoint()
{
	local OUTPUT="$1"
	local URL="$2"
	local OPTIONAL="$3"
	local EXPECTED_HTTPCODE="$4"

	local HTTPCODE EXITCODE

	local CURLOPTS="$CURL_OPTIONS"
	[ -n "$DEBUG" ] && CURLOPTS="${CURLOPTS/ -s/} -S"

	local ARGS="$URL_ARGS"

	# Special case timeout for event-feed NDJSON endpoint.
	if [ "${URL/event-feed/}" != "$URL" ]; then
		TIMEOUT=1
		CURLOPTS="$CURLOPTS -N --max-time ${FEED_TIMEOUT}"
	fi

	HTTPCODE=$(curl $CURLOPTS -w "%{http_code}\n" -o "$OUTPUT" "${URL}${ARGS:+?$ARGS}")
	EXITCODE="$?"

	if [ -n "$EXPECTED_HTTPCODE" ]; then
		if [ "$HTTPCODE" -ne "$EXPECTED_HTTPCODE" ]; then
			verbose "Warning: curl returned HTTP status $HTTPCODE != $EXPECTED_HTTPCODE for '$URL'."
			return 1;
		fi
		return 0
	fi

	if [ $EXITCODE -eq 28 ]; then # timeout
		if [ -z "$TIMEOUT" ]; then
			verbose "Warning: curl request timed out for '$URL'."
			return $EXITCODE
		fi
	elif [ $EXITCODE -ne 0 ]; then
		verbose "Warning: curl returned exitcode $EXITCODE for '$URL'."
		return $EXITCODE
	elif [ $HTTPCODE -ne 200 ]; then
		[ -n "$OPTIONAL" ] || verbose "Warning: curl returned HTTP status $HTTPCODE for '$URL'."
		return 1
	elif [ ! -e "$OUTPUT" -o ! -s "$OUTPUT" ]; then
		[ -n "$OPTIONAL" ] || verbose "Warning: no or empty file downloaded by curl."
		return 1
	fi
	return 0
}

validate_schema()
{
	local DATA="$1" SCHEMA="$2" RESULT EXITCODE

	RESULT=$(${VALIDATE_JSON:-validate-json} "$DATA" "$SCHEMA")
	EXITCODE=$?
	verbose '%s' "$RESULT"
	if [ $EXITCODE -eq 0 ]; then
		verbose 'OK'
	else
		verbose ''
	fi
	return $EXITCODE
}

# Copy schema files so we can modify common.json for the non-empty option
cp -a "$MYDIR/json-schema" "$TMP"

if [ -n "$NONEMPTY" ]; then
	# Don't understand why the first '\t' needs a double escape...
	sed -i '/"nonemptyarray":/a \\t\t"minItems": 1' "$TMP/json-schema/common.json"
fi
if [ -z "$EXTRAPROP" ]; then
	sed -i '/"strictproperties":/a \\t\t"additionalProperties": false' "$TMP/json-schema/common.json"
fi

# First validate and get all contests
ENDPOINT='contests'
URL="${API_URL%/}/$ENDPOINT"
SCHEMA="$TMP/json-schema/$ENDPOINT.json"
OUTPUT="$TMP/$ENDPOINT.json"
if query_endpoint "$OUTPUT" "$URL" ; then
	verbose '%20s: ' "$ENDPOINT"
	validate_schema "$OUTPUT" "$SCHEMA"
	EXIT=$?
	[ $EXIT -ne 0 -a $EXIT -ne 23 ] && exit $EXIT
	CONTESTS=$(jq -r '.[].id' "$OUTPUT")
else
	verbose '%20s: Failed to download\n' "$ENDPOINT"
	exit 1
fi

EXITCODE=0

for CONTEST in $CONTESTS ; do
	verbose "Validating contest '$CONTEST'..."
	CONTEST_URL="${API_URL%/}/contests/$CONTEST"
	mkdir -p "$TMP/$CONTEST"

	for ENDPOINT in $ENDPOINTS ; do
		if [ "${ENDPOINTS_OPTIONAL/${ENDPOINT}/}" != "$ENDPOINTS_OPTIONAL" ]; then
			OPTIONAL=1
		else
			unset OPTIONAL
		fi

		if [ "$ENDPOINT" = 'contests' ]; then
			URL="$CONTEST_URL"
			SCHEMA="$TMP/json-schema/contest.json"
		else
			URL="$CONTEST_URL/$ENDPOINT"
			SCHEMA="$TMP/json-schema/$ENDPOINT.json"
		fi

		OUTPUT="$TMP/$CONTEST/$ENDPOINT.json"

		if query_endpoint "$OUTPUT" "$URL" $OPTIONAL ; then
			verbose '%20s: ' "$ENDPOINT"
			validate_schema "$OUTPUT" "$SCHEMA"
			if [ -n "$OPTIONAL" ]; then
				ENDPOINTS_CHECK_CONSISTENT="$ENDPOINTS_CHECK_CONSISTENT
$ENDPOINT"
			fi
			EXIT=$?
			[ $EXIT -gt $EXITCODE ] && EXITCODE=$EXIT
		else
			if [ -n "$OPTIONAL" ]; then
				verbose '%20s: Optional, not present\n' "$ENDPOINT"
			else
				verbose '%20s: Failed to download\n' "$ENDPOINT"
				[ $EXITCODE -eq 0 ] && EXITCODE=1
			fi
		fi
	done

	# Now do special case event-feed endpoint
	ENDPOINT='event-feed'
	SCHEMA="$MYDIR/json-schema/$ENDPOINT-array.json"
	OUTPUT="$TMP/$CONTEST/$ENDPOINT.json"
	URL="$CONTEST_URL/$ENDPOINT"

	if query_endpoint "$OUTPUT" "$URL" ; then
		# Delete empty lines and transform NDJSON into a JSON array.
		sed -i '/^$/d;1 s/^/[/;s/$/,/;$ s/,$/]/' "$OUTPUT"

		verbose '%20s: ' "$ENDPOINT"
		validate_schema "$OUTPUT" "$SCHEMA"
		EXIT=$?
		[ $EXIT -gt $EXITCODE ] && EXITCODE=$EXIT
		[ $EXIT -ne 0 -a -n "$DEBUG" ] && cat "$OUTPUT"
	else
		verbose '%20s: Failed to download\n' "$ENDPOINT"
	fi

	if [ -n "$CHECK_CONSISTENCY" ]; then
		eval ${EXTRAPROP:-STRICT=1} $MYDIR/check-api-consistency.php "$TMP/$CONTEST" $ENDPOINTS_CHECK_CONSISTENT
		EXIT=$?
		[ $EXIT -gt $EXITCODE ] && EXITCODE=$EXIT
	fi

done

if [ -n "$CHECK_ERRORS" ]; then
	verbose "Validating errors on missing endpoints..."
	for i in $ENDPOINTS_TO_FAIL ; do
		CODE=${i%%:*}
		ENDPOINT=${i#*:}
		URL="$CONTEST_URL/$ENDPOINT"
		verbose '%20s: ' "$ENDPOINT"
		if query_endpoint /dev/null "$URL" '' "$CODE" ; then
			verbose 'OK (returned %s)\n' "$CODE"
		else
			EXIT=1
			[ $EXIT -gt $EXITCODE ] && EXITCODE=$EXIT
		fi
	done
fi

[ -n "$DEBUG" ] || rm -rf $TMP

exit $EXITCODE
