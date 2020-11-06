#!/bin/bash

shopt -s expand_aliases
alias trace_on='set -x'
alias trace_off='{ set +x; } 2>/dev/null'

function section_start_internal() {
	echo -e "section_start:`date +%s`:$1\r\e[0K$2"
	trace_on
}

function section_end_internal() {
	echo -e "section_end:`date +%s`:$1\r\e[0K"
	trace_on
}
alias section_start='trace_off ; section_start_internal '
alias section_end='trace_off ; section_end_internal '

set -euxo pipefail

section_start setup "Setup and install"

export PS4='(${BASH_SOURCE}:${LINENO}): - [$?] $ '

DIR=$(pwd)
GITSHA=$(git rev-parse HEAD || true)

# Set up
"$( dirname "${BASH_SOURCE[0]}" )"/base.sh

# Add jury to dummy user
echo "INSERT INTO userrole (userid, roleid) VALUES (3, 2);" | mysql domjudge

# Add netrc file for dummy user login
echo "machine localhost login dummy password dummy" > ~/.netrc

LOGFILE="/opt/domjudge/domserver/webapp/var/log/prod.log"

function log_on_err() {
	echo -e "\\n\\n=======================================================\\n"
	echo "Symfony log:"
	if sudo test -f "$LOGFILE" ; then
		sudo cat "$LOGFILE"
	fi
}

trap log_on_err ERR

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

# configure and restart php-fpm
sudo cp /opt/domjudge/domserver/etc/domjudge-fpm.conf "/etc/php/7.4/fpm/pool.d/domjudge-fpm.conf"
sudo /usr/sbin/php-fpm7.4

section_end setup

ADMINPASS=$(cat etc/initial_admin_password.secret)
export COOKIEJAR
COOKIEJAR=$(mktemp --tmpdir)
export CURLOPTS="--fail -sq -m 30 -b $COOKIEJAR"

# Make an initial request which will get us a session id, and grab the csrf token from it
CSRFTOKEN=$(curl $CURLOPTS -c $COOKIEJAR "http://localhost/domjudge/login" 2>/dev/null | sed -n 's/.*_csrf_token.*value="\(.*\)".*/\1/p')
# Make a second request with our session + csrf token to actually log in
curl $CURLOPTS -c $COOKIEJAR -F "_csrf_token=$CSRFTOKEN" -F "_username=admin" -F "_password=$ADMINPASS" "http://localhost/domjudge/login"

cd $DIR

cp $COOKIEJAR cookies.txt
sed -i 's/#HttpOnly_//g' cookies.txt
sed -i 's/\t0\t/\t1999999999\t/g' cookies.txt
FOUNDERR=0
ACCEPTEDERRTOTAL=0
ACCEPTEDERR=5

for url in public
do
	mkdir $url
	cd $url
    	cp $DIR/cookies.txt ./
	httrack http://localhost/domjudge/$url --assume html=text/html -*jury* -*doc* -*logout*
	cd $DIR
	for file in `find $url -name *.html`
	do
		section_start ${file//\//} $file
		# T is reasonable amount of errors to allow to not break
		su domjudge -c "/node_modules/.bin/pa11y -T $ACCEPTEDERR -E '#menuDefault > a' --reporter json ./$file" | python3 -m json.tool
	        ERR=`su domjudge -c "/node_modules/.bin/pa11y -T $ACCEPTEDERR -E '#menuDefault > a' --reporter csv ./$file" | wc -l`
		FOUNDERR=$((ERR+FOUNDERR-1)) # Remove header row
		section_end $file
	done
done
# Do not hard error yet
echo "Found: " $FOUNDERR
[ "$FOUNDERR" -le "$ACCEPTEDERRTOTAL" ]
