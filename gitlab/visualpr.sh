#!/bin/bash

. gitlab/ci_settings.sh

mkdir screenshots"$1"

section_start_collap setup "Setup and install"

# Set up
"$( dirname "${BASH_SOURCE[0]}" )"/base.sh

# Add jury to demo user
echo "INSERT INTO userrole (userid, roleid) VALUES (3, 2);" | mysql domjudge

# Add netrc file for demo user login
echo "machine localhost login demo password demo" > ~/.netrc

WGETLOGFILE="$DIR"/wget.log

trap log_on_err ERR

cd /opt/domjudge/domserver

# This needs to be done before we do any submission.
# 8 hours as a helper so we can adjust contest start/endtime
TIMEHELP=$((8*60*60))
# Database changes to make the scrapes look the same.
cat <<EOF | mysql domjudge
DELETE FROM clarification;
UPDATE contest SET activatetime = UNIX_TIMESTAMP(STR_TO_DATE('Dec 24 2008 01:30AM', '%M %d %Y %h:%i%p')) WHERE cid = 2;
UPDATE contest SET starttime    = UNIX_TIMESTAMP(STR_TO_DATE('Dec 24 2008 01:30AM', '%M %d %Y %h:%i%p')) WHERE cid = 2;
UPDATE contest SET freezetime   = UNIX_TIMESTAMP(STR_TO_DATE('Dec 24 2008 9:30AM', '%M %d %Y %h:%i%p')) WHERE cid = 2;
UPDATE contest SET endtime      = UNIX_TIMESTAMP(STR_TO_DATE('Dec 24 2008 5:30PM', '%M %d %Y %h:%i%p')) WHERE cid = 2;
UPDATE team_category SET visible = 1;
EOF

# configure and restart php-fpm
sudo cp /opt/domjudge/domserver/etc/domjudge-fpm.conf \
    "/etc/php/7.4/fpm/pool.d/domjudge-fpm.conf"
sudo /usr/sbin/php-fpm7.4

section_end setup

if [ "$ROLE" != "admin" ]; then
    # Remove admin from admin user
    echo "DELETE FROM userrole WHERE userid=1;" | mysql domjudge
fi
if [ "$ROLE" == "team" ] || [ "$URL" == "team" ]; then
    # Add team to admin user
    echo "INSERT INTO userrole (userid, roleid) VALUES (1, 3);" | mysql domjudge
    echo "UPDATE user SET teamid = 1 WHERE userid = 1;" | mysql domjudge
fi
if [ "$ROLE" == "jury" ]; then
    # Add jury to admin user
    echo "INSERT INTO userrole (userid, roleid) VALUES (1, 2);" | mysql domjudge
fi
if [ "$ROLE" == "balloon" ]; then
    # Add balloon to admin user
    echo "INSERT INTO userrole (userid, roleid) VALUES (1, 4);" | mysql domjudge
fi

# Patch some visibility problems
cp "$DIR"/gitlab/visualreg.css webapp/public/css/custom/
webapp/bin/console cache:clear

ADMINPASS=$(cat etc/initial_admin_password.secret)
if [ "$ROLE" == "none" ]; then
    # Stop the login
    ADMINPASS="NoLogin"
fi
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
if [ "$ROLE" != "none" ]; then
    curl $CURLOPTS -c $COOKIEJAR \
        -F "_csrf_token=$CSRFTOKEN" \
        -F "_username=admin" \
        -F "_password=$ADMINPASS" "http://localhost/domjudge/login"
fi

cd "$DIR"

STORAGEDIR=$DIR/screenshots"$1"/"$URL"/"$ROLE"
mkdir -p "$STORAGEDIR"

Xvfb :0 -screen 0  1920x1080x24 &
export DISPLAY=:0
cp "$COOKIEJAR" cookies.txt
sed -i 's/#HttpOnly_//g' cookies.txt
sed -i 's/\t0\t/\t1999999999\t/g' cookies.txt
mkdir -p html/"$URL"/"$ROLE"
cd html/"$URL"/"$ROLE"
cp "$DIR"/cookies.txt ./

# Database changes to make the scrapes look the same.
cat <<EOF | mysql domjudge
UPDATE auditlog SET logtime = UNIX_TIMESTAMP(STR_TO_DATE('Dec 24 2008 01:30AM', '%M %d %Y %h:%i%p'));
UPDATE user SET first_login = UNIX_TIMESTAMP(STR_TO_DATE('Dec 24 2008 01:30AM', '%M %d %Y %h:%i%p')) WHERE userid = 1;
UPDATE user SET last_login  = UNIX_TIMESTAMP(STR_TO_DATE('Dec 24 2008 01:30AM', '%M %d %Y %h:%i%p')) WHERE userid = 1;
EOF

section_start_collap scrape "Scrape the site with the rebuild admin user"
if [ "$URL" == "team" ]; then
    REGEX_TOGGLE=".*\/jury.*|.*\/public.*"
elif [ "$URL" == "public" ]; then
    REGEX_TOGGLE=".*\/team.*"
fi
REGEX_CONTADD=".*\/contests\/add"
REGEX_PHP=".*\/phpinfo.*"
REGEX_DOC=".*\/doc\/.*"
REGEX_LOGOUT="logout"
set +e
wget \
    --reject-regex "$REGEX_TOGGLE|$REGEX_PHP|$REGEX_CONTADD|$REGEX_DOC|$REGEX_LOGOUT" \
    --recursive \
    --waitretry 10 \
    --tries 20 \
    --no-clobber \
    --page-requisites \
    --html-extension \
    --convert-links \
    --restrict-file-names=windows \
    --domains localhost \
    --no-parent \
    --content-on-error \
    --rejected-log="$WGETLOGFILE" \
    --load-cookies cookies.txt \
    "http://localhost/domjudge/$URL" || true
RET=$?
set -e
#https://www.gnu.org/software/wget/manual/html_node/Exit-Status.html
# Exit code 4 is network error which we can ignore
# Exit code 6 is authentication which we hit in case of the public user
# Exit code 8 is error which should be fixed, but in different PR
if [ $RET -ne 4 ] && [ $RET -ne 8 ] && [ $RET -ne 0 ]; then
    exit $RET
fi
section_end scrape

section_start_collap removecommithash "Remove HEAD hash from files"
while IFS= read -r -d '' file; do
    newname=${file%@v=*}
    echo $newname
    mv $file $newname
done < <(find ./ -type f -name "*@v=*" -print0)
section_end removecommithash

section_start_collap cpstatic "Store scraped files in nginx"
cd "$DIR"
mkdir /var/www/html/"$URL"/
cp -r html/"$URL"/"$ROLE"/localhost/domjudge/* /var/www/html/"$URL"/
cp "$DIR"/gitlab/default-nginx /etc/nginx/sites-enabled/default
service nginx restart
section_end cpstatic

section_start_collap capture "Capture the pages in a static webserver"
# shellcheck disable=SC2089,SC2090
SKIPPED="grep -v '\.eot\|\.ttf\|\.woff*\|\.js@*'"
cd html/"$URL"/"$ROLE"/localhost/domjudge
# shellcheck disable=SC2090
for file in $(find ./ -type f|$SKIPPED); do
    urlpath="${file//.\//}"
    # Small risk of collision
    storepath=$(sed "s/\//_s_/g"<<<"$urlpath")
    cutycapt --delay=2000 --min-width=1920 --min-height=1080 \
        --url="http://localhost/$URL/$urlpath" \
        --out="$STORAGEDIR/$storepath-ff.png"
done
section_end capture

