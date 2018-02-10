#!/bin/bash -ex

export PS4='(${BASH_SOURCE}:${LINENO}): - [$?] $ '

DIR=$(pwd)
lsb_release -a

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
    domjudge.useexternalids: false
    # Needs a version number
    domjudge.version: 0.0.dummy
    domjudge.tmpdir: /tmp
EOF

# install all php dependencies
export SYMFONY_ENV="prod"
composer install

# run phpunit tests
lib/vendor/bin/phpunit webapp

# downgrade java version outside of chroot since this didn't work
sudo apt-get remove -y openjdk-8-jdk openjdk-8-jre openjdk-8-jre-headless oracle-java8-installer oracle-java9-installer

# delete apport if exists
sudo apt-get remove -y apport

# configure, make and install (but skip documentation)
make configure
./configure --disable-doc-build --with-baseurl='http://localhost/domjudge/'
make domserver judgehost
sudo make install-domserver install-judgehost

# setup database and add special user
cd /opt/domjudge/domserver
sudo bin/dj_setup_database install
echo "INSERT INTO user (userid, username, name, password, teamid) VALUES (3, 'dummy', 'dummy user for example team', '\$2y\$10\$0d0sPmeAYTJ/Ya7rvA.kk.zvHu758ScyuHAjps0A6n9nm3eFmxW2K', 2)" | sudo mysql domjudge
echo "INSERT INTO userrole (userid, roleid) VALUES (3, 2);" | sudo mysql domjudge
echo "INSERT INTO userrole (userid, roleid) VALUES (3, 3);" | sudo mysql domjudge
echo "machine localhost login dummy password dummy" > ~/.netrc

# configure and restart nginx
sudo rm -f /etc/nginx/sites-enabled/*
sudo cp /opt/domjudge/domserver/etc/nginx-conf /etc/nginx/sites-enabled/domjudge
sudo service nginx restart

# configure and restart php-fpm
sudo cp /opt/domjudge/domserver/etc/domjudge-fpm.conf "$HOME/.phpenv/versions/$(phpenv version-name)/etc/php-fpm.d/"
sudo "$HOME/.phpenv/versions/$(phpenv version-name)/sbin/php-fpm"

# add users/group for judgedaemons (FIXME: make them configurable)
sudo useradd -d /nonexistent -g nogroup -s /bin/false domjudge-run-0
sudo useradd -d /nonexistent -g nogroup -s /bin/false domjudge-run-1
sudo groupadd domjudge-run

# configure judgehost
cd /opt/domjudge/judgehost/
sudo cp /opt/domjudge/judgehost/etc/sudoers-domjudge /etc/sudoers.d/
sudo chmod 400 /etc/sudoers.d/sudoers-domjudge
sudo bin/create_cgroups

# build chroot
cd ${DIR}/misc-tools
sudo ./dj_make_chroot -a amd64

# download domjudge-scripts for API check
cd $HOME
composer -n require justinrainbow/json-schema
PATH=${PATH}:${HOME}/vendor/bin
git clone --depth=1 https://github.com/DOMjudge/domjudge-scripts.git
CHECK_API=${HOME}/domjudge-scripts/contest-api/check-api.sh

# start eventdaemon
cd /opt/domjudge/domserver/
bin/eventdaemon &

# start judgedaemon
cd /opt/domjudge/judgehost/
bin/judgedaemon -n 0 &

# write out current log to learn why it might be broken
sleep 5s && cat /var/log/nginx/domjudge.log


# submit test programs
cd ${DIR}/tests
make check-syntax check test-stress

# wait for and check results
NUMSUBS=$(curl http://admin:admin@localhost/domjudge/api/v4/submissions | python -mjson.tool | grep -c '"id":')
export COOKIEJAR
COOKIEJAR=$(mktemp --tmpdir)
export CURLOPTS="-sq -m 30 -b $COOKIEJAR"

# Make an initial request which will get us a session id, and grab the csrf token from it
CSRFTOKEN=$(curl $CURLOPTS -c $COOKIEJAR "http://localhost/domjudge/login" 2>/dev/null | sed -n 's/.*_csrf_token.*value="\(.*\)".*/\1/p')
# Make a second request with our session + csrf token to actually log in
curl $CURLOPTS -c $COOKIEJAR -F "_csrf_token=$CSRFTOKEN" -F "_username=admin" -F "_password=admin" "http://localhost/domjudge/login"

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

# check the Contest API, non-failing for now
$CHECK_API http://admin:admin@localhost/domjudge/api/contests/2 || true
