#!/bin/bash
set -euo pipefail
BASEDIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )

ARG1=${1:-}
if [[ $ARG1 == "--help" || $ARG1 == "-h" ]]; then
  echo "Usage: $0 <DOMJUDGE_URL> <CONTESTID>"
  echo ""
  echo "<DOMJUDGE_URL>    The base URL of your domjudge installation"
  echo "                  (default: http://localhost/domjudge)"
  echo "<CONTESTID>       The contest id you want to validate(default: 2)"
  exit 1
fi

DOMJUDGE_URL="${1:-http://localhost/domjudge}"
DOMJUDGE_CID="${2:-2}"
echo "Starting CDS validation of contest $DOMJUDGE_CID on $DOMJUDGE_URL"
echo ""
CCS_URL="${DOMJUDGE_URL}/api/contests/$DOMJUDGE_CID"
CCS_USER="admin"
CCS_PASS=$(cat ../etc/initial_admin_password.secret)



# ----------------------------------------------------------------------------
CDS_ADMIN_PASS="admin"
CDS_BALLOON_PASS="balloon"
CDS_PUBLIC_PASS="public"
CDS_PRESENTATION_PASS="presentation"
CDS_MYICPC_PASS="myicpc"
CDS_LIVE_PASS="live"

CDS_VERSION="2.1.1992"
UTILS_VERSION="2.1.1992"
CDS_URL="https://pc2.ecs.csus.edu/pc2projects/build/CDS/dist/wlp.CDS-${CDS_VERSION}.zip"
UTILS_URL="https://pc2.ecs.csus.edu/pc2projects/build/ContestUtil/dist/contestUtil-$UTILS_VERSION.zip"

wait_for_quiet() {
  FILE_TO_WATCH="$1"
  DELAY="${2:-5}"

  echo "Waiting for ${DELAY}s of inactivity for file: $(basename $FILE_TO_WATCH)"

  LAST_SIZE="-1"
  SIZE="$(stat -c%s "$FILE_TO_WATCH")"
  while (( LAST_SIZE < SIZE )); do
    if (( LAST_SIZE > 0 )); then
      echo "    file has size $SIZE(was $LAST_SIZE), still changing"
    else
      echo "    file has size $SIZE"
    fi
    sleep $DELAY
    LAST_SIZE="$SIZE"
    SIZE="$(stat -c%s "$FILE_TO_WATCH")"
  done
}


echo "Checking for cds"
CDS_DIR="$BASEDIR/icpctools/cds-$CDS_VERSION"
if [ ! -d "$CDS_DIR" ]; then

  mkdir -p icpctools/source_archives
  SOURCE_ARCHIVE="icpctools/source_archives/icpctools-cds-$CDS_VERSION.zip"
  if [ ! -f $SOURCE_ARCHIVE ]; then
    echo "    Downloading cds($CDS_VERSION)..."
    curl -L --insecure "$CDS_URL" -o $SOURCE_ARCHIVE >/dev/null 2>&1
  fi

  echo "    Extracting cds..."
  TMPDIR="$(mktemp -d)"
  unzip -d $TMPDIR $SOURCE_ARCHIVE >/dev/null 2>&1
  mv $TMPDIR/wlp $CDS_DIR
  rm -r "$TMPDIR"
fi
echo "CDS present"


echo "Checking for contest utils"
CONTESTUTIL_DIR="$BASEDIR/icpctools/contestutil-$UTILS_VERSION"
if [ ! -d "$CONTESTUTIL_DIR" ]; then

  mkdir -p icpctools/source_archives
  SOURCE_ARCHIVE="icpctools/source_archives/icpctools-contestutil-$UTILS_VERSION.zip"
  if [ ! -f $SOURCE_ARCHIVE ]; then
    echo "    Downloading contest utils($UTILS_VERSION)..."
    curl -L --insecure "$UTILS_URL" -o $SOURCE_ARCHIVE >/dev/null 2>&1
  fi

  echo "    Extracting contest utils..."
  TMPDIR="$(mktemp -d)"
  unzip -d $TMPDIR $SOURCE_ARCHIVE >/dev/null 2>&1
  mv $TMPDIR/contestUtil-2.1 $CONTESTUTIL_DIR
  rm -r "$TMPDIR"
fi
echo "Contest utils present"


echo "Checking for CDP directory"
CDP_DIR="$BASEDIR/icpctools/cdp"
if [ -d "$CDP_DIR" ]; then
  echo "    cdp directory present, deleting"
  # For some reason on my system teh first rm fails
  rm -rf "$CDP_DIR" 2>/dev/null || true
  sleep 1
  rm -rf "$CDP_DIR"
fi
mkdir -p "$CDP_DIR"

echo "Configuring CDS contest"
cat <<EOF > "$CDS_DIR/usr/servers/cds/config/cdsConfig.xml"
<cds>
	<contest location="$BASEDIR/icpctools/cdp">
		<ccs url="$CCS_URL" user="$CCS_USER" password="$CCS_PASS"/>
	</contest>
</cds>
EOF

echo "Configuring CDS users"
cat <<EOF > "$CDS_DIR/usr/servers/cds/users.xml"
<server description="ACM ICPC contest clients">
   <!-- Change passwords immediately to secure the contest -->
   <basicRegistry>
      <user name="admin" password="$CDS_ADMIN_PASS"/>
      <user name="balloon" password="$CDS_BALLOON_PASS"/>
      <user name="public" password="$CDS_PUBLIC_PASS"/>
      <user name="presentation" password="$CDS_PRESENTATION_PASS"/>
      <user name="myicpc" password="$CDS_MYICPC_PASS"/>
      <user name="live" password="$CDS_LIVE_PASS"/>
   </basicRegistry>
</server>
EOF

cd $BASEDIR/icpctools # cd so the logs directory ends up in a less annoying place

echo "Start the cds"
CDS="$CDS_DIR/bin/server run cds"
$CDS >cds.log 2>&1 &
CDS_PID=$!

# Wait for the log file to be quiet for 5 seconds
wait_for_quiet cds.log 10
echo "CDS initialized"

echo "Load the CDS Contest endpoint to make it start reading the event feed"
curl -k --connect-timeout 5 --max-time 10 -u admin:$CDS_ADMIN_PASS https://localhost:8443/api/contests/$DOMJUDGE_CID >/dev/null 2>&1
wait_for_quiet cds.log 10

# Clear old files from previous runs
rm -f cds-events.json cds-scoreboard.json dj-events.json dj-scoreboard.json

echo "Getting the eventfeed from the cds(please be patient)"
curl -s -k --connect-timeout 5 --no-buffer -u admin:$CDS_ADMIN_PASS https://localhost:8443/api/contests/$DOMJUDGE_CID/event-feed > cds-events.json 2>/dev/null &
CURL_PID=$!
wait_for_quiet cds-events.json
# shellcheck disable=SC2015
{ kill $CURL_PID && wait $CURL_PID || true; } >/dev/null 2>&1


echo "Get the event-feed from domjudge(please be patient)"
curl -s -k --connect-timeout 5 --no-buffer -u $CCS_USER:$CCS_PASS $CCS_URL/event-feed > dj-events.json 2>/dev/null &
CURL_PID=$!
wait_for_quiet dj-events.json
# shellcheck disable=SC2015
{ kill $CURL_PID && wait $CURL_PID || true; } >/dev/null 2>&1

echo "Get the scoreboard from the cds"
curl -s -k --connect-timeout 5 --no-buffer -u admin:$CDS_ADMIN_PASS https://localhost:8443/api/contests/$DOMJUDGE_CID/scoreboard > cds-scoreboard.json 2>/dev/null
echo "Get the scoreboard from domjudge"
curl -s -k --connect-timeout 5 --no-buffer -u $CCS_USER:$CCS_PASS $CCS_URL/scoreboard > dj-scoreboard.json 2>/dev/null



echo "Stopping cds"
# shellcheck disable=SC2015
{ kill $CDS_PID && wait $CDS_PID || true; } >/dev/null 2>&1
echo "    stopped"

echo "Comparing the eventfeeds"
set +e
$CONTESTUTIL_DIR/eventFeed.sh --compare cds-events.json dj-events.json
EVENTFEED_CHECK=$?
set -e

echo "Comparing the scoreboards"
set +e
$CONTESTUTIL_DIR/scoreboardUtil.sh cds-scoreboard.json dj-scoreboard.json
SCOREBOARD_CHECK=$?
set -e

RET=0
if [ $SCOREBOARD_CHECK -ne 0 ]; then
  echo "Scoreboard comparison failed"
  RET=1
fi
if [ $EVENTFEED_CHECK -ne 0 ]; then
  echo "Event-Feed comparison failed"
  RET=1
fi
exit $RET
