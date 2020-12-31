#!/bin/bash

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

mkdir screenshots$1
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
    # We have access to the host date/time, reset this back
    # service ntp stop
    # ntpd -gq
    # service ntp start
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
# Add team to admin user
echo "INSERT INTO userrole (userid, roleid) VALUES (1, 3);" | mysql domjudge
echo "UPDATE user SET teamid = 1 WHERE userid = 1;" | mysql domjudge
# Add jury to admin user
echo "INSERT INTO userrole (userid, roleid) VALUES (1, 2);" | mysql domjudge
# Add balloon to admin user
echo "INSERT INTO userrole (userid, roleid) VALUES (1, 4);" | mysql domjudge
# configure and restart php-fpm
sudo cp /opt/domjudge/domserver/etc/domjudge-fpm.conf "/etc/php/7.4/fpm/pool.d/domjudge-fpm.conf"
sudo /usr/sbin/php-fpm7.4

section_end setup

# Patch some visibility problems
cp $DIR/gitlab/visualreg.css webapp/public/css/custom/
webapp/bin/console cache:clear

ADMINPASS=$(cat etc/initial_admin_password.secret)
export COOKIEJAR
COOKIEJAR=$(mktemp --tmpdir)
export CURLOPTS="--fail -sq -m 30 -b $COOKIEJAR"

# First commit I could find, every date should be fine, time should be stable
# between PR and Master to get semi stable screenshots
# Breaks so removed for now
# date -s "18 May 2004 12:05:57"

# Make an initial request which will get us a session id, and grab the csrf token from it
CSRFTOKEN=$(curl $CURLOPTS -c $COOKIEJAR "http://localhost/domjudge/login" 2>/dev/null | sed -n 's/.*_csrf_token.*value="\(.*\)".*/\1/p')
# Make a second request with our session + csrf token to actually log in
curl $CURLOPTS -c $COOKIEJAR -F "_csrf_token=$CSRFTOKEN" -F "_username=admin" -F "_password=$ADMINPASS" "http://localhost/domjudge/login"

cd $DIR

STORAGEDIR=screenshots$1
mkdir -p $STORAGEDIR

Xvfb :0 -screen 0  1920x1080x24 &
export DISPLAY=:0
cp $COOKIEJAR cookies.txt
sed -i 's/#HttpOnly_//g' cookies.txt
sed -i 's/\t0\t/\t1999999999\t/g' cookies.txt
for url in public
do
    mkdir $url
    cd $url
    cp $DIR/cookies.txt ./
    httrack http://localhost/domjudge/$url --assume html=text/html -*doc* -*/team/* -*/jury/* -*logout*
    cd $DIR
    mkdir /var/www/html/$url/
    cp -r $url/localhost/domjudge/* /var/www/html/$url/
    cp $DIR/gitlab/default-nginx /etc/nginx/sites-enabled/default
    service nginx restart
    for file in `find $url -type f -name "*.html"`
    do
        prefix="^$url\/localhost\/domjudge\/"
        urlpath=$(sed "s/$prefix//g"<<<$file)
        # Small risk of collision
        storepath=$(sed "s/\//_s_/g"<<<$urlpath)
	cutycapt --delay=2000 --min-width=1920 --min-height=1080 --url=http://localhost/$url/$urlpath --out=$STORAGEDIR/$storepath-ff.png
    done
done
