#!/bin/bash

gitlabartifacts="$(pwd)/gitlabartifacts"
mkdir -p "$gitlabartifacts"

shopt -s expand_aliases
alias trace_on='set -x'
alias trace_off='{ set +x; } 2>/dev/null'

function section_start_internal() {
	echo -e "section_start:`date +%s`:$1[collapsed=true]\r\e[0K$2"
	trace_on
}

function section_end_internal() {
	echo -e "section_end:`date +%s`:$1\r\e[0K"
	trace_on
}

alias section_start='trace_off ; section_start_internal '
alias section_end='trace_off ; section_end_internal '

set -euxo pipefail
DIR=$(pwd)

function finish() {
	echo -e "\\n\\n=======================================================\\n"
	echo "Storing artifacts..."
	trace_on
	set +e
	mysqldump domjudge > "$gitlabartifacts/db.sql"
	cp /var/log/nginx/domjudge.log "$gitlabartifacts/nginx.log"
	cp /opt/domjudge/domserver/webapp/var/log/prod.log "$gitlabartifacts/symfony.log"
	cp /tmp/judgedaemon.log "$gitlabartifacts/judgedaemon.log"
	cp /proc/cmdline "$gitlabartifacts/cmdline"
	cp /chroot/domjudge/etc/apt/sources.list "$gitlabartifacts/sources.list"
	cp /chroot/domjudge/debootstrap/debootstrap.log "$gitlabartifacts/debootstrap.log"
	cp "${DIR}/misc-tools/icpctools/*json" "$gitlabartifacts/"
}
trap finish EXIT

section_start setup "Setup and install"

export PS4='(${BASH_SOURCE}:${LINENO}): - [$?] $ '

GITSHA=$(git rev-parse HEAD || true)

# Set up
"$( dirname "${BASH_SOURCE[0]}" )"/base.sh

# Add jury to dummy user
echo "INSERT INTO userrole (userid, roleid) VALUES (3, 2);" | mysql domjudge

# Add netrc file for dummy user login
echo "machine localhost login dummy password dummy" > ~/.netrc

cd /opt/domjudge/domserver

# This needs to be done before we do any submission.
# 8 hours as a helper so we can adjust contest start/endtime
TIMEHELP=$((8*60*60))
# Database changes to make the REST API and event feed match better.
cat <<EOF | mysql domjudge
DELETE FROM clarification;
UPDATE contest SET starttime  = UNIX_TIMESTAMP()-$TIMEHELP WHERE cid = 2;
UPDATE contest SET freezetime = UNIX_TIMESTAMP()+15        WHERE cid = 2;
UPDATE contest SET endtime    = UNIX_TIMESTAMP()+$TIMEHELP WHERE cid = 2;
UPDATE team_category SET visible = 1;
EOF

ADMINPASS=$(cat etc/initial_admin_password.secret)
cp etc/initial_admin_password.secret "$gitlabartifacts/"

# configure and restart php-fpm
sudo cp /opt/domjudge/domserver/etc/domjudge-fpm.conf "/etc/php/7.4/fpm/pool.d/domjudge-fpm.conf"
sudo /usr/sbin/php-fpm7.4

section_end setup

section_start submit_client "Test submit client"
cd ${DIR}/submit
make check-full
section_end submit_client

section_start judgehost "Configure judgehost"
cd /opt/domjudge/judgehost/
sudo cp /opt/domjudge/judgehost/etc/sudoers-domjudge /etc/sudoers.d/
sudo chmod 400 /etc/sudoers.d/sudoers-domjudge
sudo bin/create_cgroups

if [ ! -d ${DIR}/chroot/domjudge/ ]; then
	cd ${DIR}/misc-tools
	time sudo ./dj_make_chroot -a amd64 |& tee "$gitlabartifacts/dj_make_chroot.log"
fi
section_end judgehost

section_start more_setup "Remaining setup (e.g. starting judgedaemon)"
# download domjudge-scripts for API check
cd $HOME
composer -n require justinrainbow/json-schema
echo -e "\033[0m"
PATH=${PATH}:${HOME}/vendor/bin
git clone --depth=1 https://github.com/DOMjudge/domjudge-scripts.git
CHECK_API=${HOME}/domjudge-scripts/contest-api/check-api.sh

# Recreate domjudge-run-0 user with random UID to prevent clashes with
# existing users in the host and other CI jobs, which can lead to
# unforeseen process limits being hit.
sudo userdel -f -r domjudge-run-0
sudo useradd -d /nonexistent -g nogroup -s /bin/false -u $((2000+(RANDOM%1000))) domjudge-run-0

# start judgedaemon
cd /opt/domjudge/judgehost/

# Since ubuntu20.04 gitlabci image this is sometimes needed
# It should be safe to remove this when it creates issues
set +e
mount -t proc proc /proc
set -e

sudo -u domjudge bin/judgedaemon -n 0 |& tee /tmp/judgedaemon.log &
sleep 5

section_end more_setup

section_start submitting "Submitting test sources (including Kattis example)"
cd ${DIR}/tests
make check test-stress

# Prepare to load example problems from Kattis/problemtools
echo "INSERT INTO userrole (userid, roleid) VALUES (3, 1);" | mysql domjudge
cd /tmp
git clone --depth=1 https://github.com/Kattis/problemtools.git
cd problemtools/examples
mv hello hello_kattis
for i in hello_kattis different guess; do
	(
		cd "$i"
		zip -r "../${i}.zip" -- *
	)
	curl --fail -X POST -n -N -F zip[]=@${i}.zip http://localhost/domjudge/api/contests/2/problems
done
section_end submitting

section_start judging "Waiting until all submissions are judged"
# wait for and check results
NUMSUBS=$(curl --fail http://admin:$ADMINPASS@localhost/domjudge/api/contests/2/submissions | python3 -mjson.tool | grep -c '"id":')
export COOKIEJAR
COOKIEJAR=$(mktemp --tmpdir)
export CURLOPTS="--fail -sq -m 30 -b $COOKIEJAR"

# Make an initial request which will get us a session id, and grab the csrf token from it
CSRFTOKEN=$(curl $CURLOPTS -c $COOKIEJAR "http://localhost/domjudge/login" 2>/dev/null | sed -n 's/.*_csrf_token.*value="\(.*\)".*/\1/p')
# Make a second request with our session + csrf token to actually log in
curl $CURLOPTS -c $COOKIEJAR -F "_csrf_token=$CSRFTOKEN" -F "_username=admin" -F "_password=$ADMINPASS" "http://localhost/domjudge/login"

# Send a general clarification to later test if we see the event.
curl $CURLOPTS -F "sendto=" -F "problem=2-" -F "bodytext=Testing" -F "submit=Send" \
	 "http://localhost/domjudge/jury/clarifications/send" -o /dev/null

# Don't spam the log.
set +x

while /bin/true; do
	sleep 30s
	curl $CURLOPTS "http://localhost/domjudge/jury/judging-verifier?verify_multiple=1" -o /dev/null

	# Check if we are done, i.e. everything is judged or something got disabled by internal error...
	if tail /tmp/judgedaemon.log | grep -q "No submissions in queue"; then
		break
	fi
	# ... or something has crashed.
	if ! pgrep -f judgedaemon; then
		break
	fi
done

NUMNOTVERIFIED=$(curl $CURLOPTS "http://localhost/domjudge/jury/judging-verifier" | grep "submissions checked"     | sed -r 's/^.* ([0-9]+) submissions checked.*$/\1/')
NUMVERIFIED=$(   curl $CURLOPTS "http://localhost/domjudge/jury/judging-verifier" | grep "submissions not checked" | sed -r 's/^.* ([0-9]+) submissions not checked.*$/\1/')
NUMNOMAGIC=$(    curl $CURLOPTS "http://localhost/domjudge/jury/judging-verifier" | grep "without magic string"    | sed -r 's/^.* ([0-9]+) without magic string.*$/\1/')
section_end judging

# We expect
# - two submissions with ambiguous outcome,
# - no submissions without magic string,
# - and all submissions to be judged.
if [ $NUMNOTVERIFIED -ne 2 ] || [ $NUMNOMAGIC -ne 0 ] || [ $NUMSUBS -gt $((NUMVERIFIED+NUMNOTVERIFIED)) ]; then
	section_start error "Short error description"
	# We error out below anyway, so no need to fail earlier than that.
	set +e
	echo "verified subs: $NUMVERIFIED, unverified subs: $NUMNOTVERIFIED, total subs: $NUMSUBS"
	echo "(expected 2 submissions to be unverified, but all to be processed)"
	echo "Of these $NUMNOMAGIC do not have the EXPECTED_RESULTS string (should be 0)."
	curl $CURLOPTS "http://localhost/domjudge/jury/judging-verifier?verify_multiple=1" | w3m -dump -T text/html
	section_end error

	section_start logfiles "All the more or less useful logfiles"
	for i in /opt/domjudge/judgehost/judgings/*/*/*/*/*/compile.out; do
		echo $i;
		head -n 100 $i;
		dir=$(dirname $i)
		if [ -r $dir/testcase001/system.out ]; then
			head $dir/testcase001/system.out
			head $dir/testcase001/runguard.err
			head $dir/testcase001/program.err
			head $dir/testcase001/program.meta
		fi
		echo;
	done
	exit -1;
fi

section_start api_check "Performing API checks"
# Start logging again
set -x

# Delete contest so API check does not fail because of empty results.
echo "DELETE FROM contest WHERE cid=1" | mysql domjudge

# Check the Contest API:
$CHECK_API -n -C -e -a 'strict=1' http://admin:$ADMINPASS@localhost/domjudge/api
section_end api_check |& tee "$gitlabartifacts/check_api.log"

section_start validate_feed "Validate the eventfeed against API (ignoring failures)"
cd ${DIR}/misc-tools
./compare-cds.sh http://localhost/domjudge 2 |& tee "$gitlabartifacts/compare_cds.log" || true
section_end validate_feed
