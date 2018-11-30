#!/bin/bash -ex

export PS4='(${BASH_SOURCE}:${LINENO}): - [$?] $ '

DIR=$(pwd)
lsb_release -a

GITSHA=$(git rev-parse HEAD || true)

# FIXME: This chicken-egg problem is annoying but let us bootstrap for now.
echo "CREATE DATABASE IF NOT EXISTS \`domjudge\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | mysql
echo "GRANT SELECT, INSERT, UPDATE, DELETE ON \`domjudge\`.* TO 'domjudge'@'localhost' IDENTIFIED BY 'domjudge';" | mysql

# Generate a parameters yml file for symfony
cat > webapp/app/config/parameters.yml <<EOF
parameters:
    database_host: 127.0.0.1
    database_port: ~
    database_name: domjudge
    database_user: domjudge
    database_password: domjudge
    mailer_transport: smtp
    mailer_host: 127.0.0.1
    mailer_user: ~
    mailer_password: ~

    # A secret key that's used to generate certain security-related tokens
    secret: ThisTokenIsNotSoSecretChangeIt

    # Additional auth methods can be enabled here. This is an array.
    # Supported values are 'ipaddress', and 'xheaders' currently.
    domjudge.authmethods: []

    # Needs a version number
    domjudge.version: 0.0.dummy
    domjudge.tmpdir: /tmp
EOF

# install all php dependencies
export SYMFONY_ENV="prod"
composer install

# configure, make and install (but skip documentation)
make configure
./configure --disable-doc-build --with-baseurl='http://localhost/domjudge/'
make build-scripts domserver judgehost
sudo make install-domserver install-judgehost

# setup database and add special user
cd /opt/domjudge/domserver
sudo bin/dj_setup_database install
echo "INSERT INTO user (userid, username, name, password, teamid) VALUES (3, 'dummy', 'dummy user for example team', '\$2y\$10\$0d0sPmeAYTJ/Ya7rvA.kk.zvHu758ScyuHAjps0A6n9nm3eFmxW2K', 2)" | mysql domjudge
echo "INSERT INTO userrole (userid, roleid) VALUES (3, 2);" | mysql domjudge
echo "INSERT INTO userrole (userid, roleid) VALUES (3, 3);" | mysql domjudge
echo "machine localhost login dummy password dummy" > ~/.netrc

# configure and restart nginx
sudo rm -f /etc/nginx/sites-enabled/*
sudo cp /opt/domjudge/domserver/etc/nginx-conf /etc/nginx/sites-enabled/domjudge
sudo /usr/sbin/nginx

# run phpunit tests
lib/vendor/bin/phpunit --stderr -c webapp/phpunit.xml.dist

# configure and restart php-fpm
sudo cp /opt/domjudge/domserver/etc/domjudge-fpm.conf "/etc/php/7.2/fpm/pool.d/domjudge-fpm.conf"
sudo /usr/sbin/php-fpm7.2


# add users/group for judgedaemons (FIXME: make them configurable)
# sudo useradd -d /nonexistent -g nogroup -s /bin/false domjudge-run-0
# sudo useradd -d /nonexistent -g nogroup -s /bin/false domjudge-run-1
# sudo groupadd domjudge-run

# configure judgehost
cd /opt/domjudge/judgehost/
sudo cp /opt/domjudge/judgehost/etc/sudoers-domjudge /etc/sudoers.d/
sudo chmod 400 /etc/sudoers.d/sudoers-domjudge
sudo bin/create_cgroups

# Provoke runguard bug where we truncate the output in rare cases.
cd ${DIR}/judge
g++ -Wall -O2 -static provoke-pipe-bug.cc
set +x
errors=0
for i in $(seq 30000); do
	sudo ./runguard -o /tmp/o -e /tmp/e -s 8 -t 1 -C 1 -p 64 -P 0 -u domjudge-run-0 -g domjudge-run ./a.out
	char_count=$(sudo wc -c /tmp/o | cut -d\   -f1)
	if [ $char_count -ne 6309 ]; then
		echo "ERROR at iteration $i, read $char_count of 6309 expected characters."
		errors=$((errors + 1))
	fi
	((i % 500 != 0)) && echo -n "iteration $i"
done
set -x
echo "${errors} truncated outputs in runguard."
if [ $errors -gt 0 ]; then
	exit -1
fi

# build chroot (randomly pick which script to use, try to use commit
# hash for reproducibility)
if [ -n "$GITSHA" ]; then
	FLIP=$(( $(printf '%d' "0x${GITSHA:0:2}") % 2 ))
else
	FLIP=$((RANDOM % 2))
fi
cd ${DIR}/misc-tools
if [ $FLIP -eq 1 ]; then
  time sudo ./dj_make_chroot -a amd64
else
  time sudo ./dj_make_chroot_docker -i domjudge/default-judgehost-chroot:latest
fi

# download domjudge-scripts for API check
cd $HOME
composer -n require justinrainbow/json-schema
PATH=${PATH}:${HOME}/vendor/bin
git clone --depth=1 https://github.com/DOMjudge/domjudge-scripts.git
CHECK_API=${HOME}/domjudge-scripts/contest-api/check-api.sh

# 8hours as a helper so we can adjust contest start/endtime
TIMEHELP=$((8*60*60))
# Database changes to make the REST API and event feed match better.
cat <<EOF | mysql domjudge
DELETE FROM clarification;
UPDATE contest SET starttime = UNIX_TIMESTAMP()-$TIMEHELP WHERE cid = 2;
UPDATE contest SET freezetime = UNIX_TIMESTAMP()+15 WHERE cid = 2;
UPDATE contest SET endtime = UNIX_TIMESTAMP()+$TIMEHELP WHERE cid = 2;
UPDATE team_category SET visible = 1;
EOF

# start eventdaemon
cd /opt/domjudge/domserver/
bin/eventdaemon &
sleep 5

# start judgedaemon
cd /opt/domjudge/judgehost/
bin/judgedaemon -n 0 &
sleep 5

# write out current log to learn why it might be broken
cat /var/log/nginx/domjudge.log

# Print the symfony log if it exists
if sudo test -f /opt/domjudge/domserver/webapp/var/logs/prod.log; then
  sudo cat /opt/domjudge/domserver/webapp/var/logs/prod.log
fi

# submit test programs
cd ${DIR}/tests
make check-syntax check test-stress

# wait for and check results
NUMSUBS=$(curl http://admin:admin@localhost/domjudge/api/contests/2/submissions | python -mjson.tool | grep -c '"id":')
export COOKIEJAR
COOKIEJAR=$(mktemp --tmpdir)
export CURLOPTS="-sq -m 30 -b $COOKIEJAR"

# Make an initial request which will get us a session id, and grab the csrf token from it
CSRFTOKEN=$(curl $CURLOPTS -c $COOKIEJAR "http://localhost/domjudge/login" 2>/dev/null | sed -n 's/.*_csrf_token.*value="\(.*\)".*/\1/p')
# Make a second request with our session + csrf token to actually log in
curl $CURLOPTS -c $COOKIEJAR -F "_csrf_token=$CSRFTOKEN" -F "_username=admin" -F "_password=admin" "http://localhost/domjudge/login"

# Send a general clarification to later test if we see the event.
curl $CURLOPTS -F "sendto=" -F "problem=2-" -F "bodytext=Testing" -F "submit=Send" \
	 "http://localhost/domjudge/jury/clarification.php" -o /dev/null

while /bin/true; do
	curl $CURLOPTS "http://localhost/domjudge/jury/check_judgings.php?verify_multiple=1" -o /dev/null
	NUMNOTVERIFIED=$(curl $CURLOPTS "http://localhost/domjudge/jury/check_judgings.php" | grep "submissions checked" | cut -d\  -f1)
	NUMVERIFIED=$(curl $CURLOPTS "http://localhost/domjudge/jury/check_judgings.php" | grep "not checked" | cut -d\  -f1)
	if [ $NUMSUBS -gt $((NUMVERIFIED+NUMNOTVERIFIED)) ]; then
		sleep 30s
	else
		break
	fi
done

NUMNOMAGIC=$(curl $CURLOPTS "http://localhost/domjudge/jury/check_judgings.php" | grep "not checked" | sed -r 's/^.* ([0-9]+) without magic string.*$/\1/')

# include debug output here
if [ $NUMNOTVERIFIED -ne 2 ] || [ $NUMNOMAGIC -ne 0 ]; then
	echo "Exactly 2 submissions are expected to be unverified, but $NUMNOTVERIFIED are."
	echo "Of these $NUMNOMAGIC do not have the EXPECTED_RESULTS string (should be 0)."
	curl $CURLOPTS "http://localhost/domjudge/jury/check_judgings.php?verify_multiple=1"
	for i in /opt/domjudge/judgehost/judgings/*/*/*/compile.out; do
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
	cat /proc/cmdline
	cat /chroot/domjudge/etc/apt/sources.list
	exit -1;
fi

# Check the Contest API:
$CHECK_API -n -C -a 'strict=1' http://admin:admin@localhost/domjudge/api

# Validate the eventfeed against the api(currently ignore failures)
cd ${DIR}/misc-tools
./compare-cds.sh http://localhost/domjudge 2 || true
