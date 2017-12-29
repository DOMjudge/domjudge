#!/bin/bash -ex

DIR=$(pwd)
lsb_release -a

# install composer per the composer docs, see:
# https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md
EXPECTED_SIGNATURE=$(wget https://composer.github.io/installer.sig -O - -q)
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_SIGNATURE=$(php -r "echo hash_file('SHA384', 'composer-setup.php');")

if [ "$EXPECTED_SIGNATURE" = "$ACTUAL_SIGNATURE" ]; then
    php composer-setup.php --filename=composer
    rm composer-setup.php
else
    >&2 echo 'ERROR: Invalid installer signature'
    rm composer-setup.php
    exit 1
fi

# install all php dependencies
./composer install --no-dev

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

# configure and restart apache
sudo cp /opt/domjudge/domserver/etc/apache.conf /etc/apache2/sites-enabled/
sudo service apache2 restart

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

# start judgedaemon
cd /opt/domjudge/judgehost/
bin/judgedaemon -n 0 &

# submit test programs
cd /${DIR}/tests
make check-syntax check test-stress

# wait for and check results
NUMSUBS=$(curl http://admin:admin@localhost/domjudge/api/submissions | python -mjson.tool | grep -c id)
export COOKIEJAR
COOKIEJAR=$(mktemp --tmpdir)
export CURLOPTS="-sq -m 30 -b $COOKIEJAR"
curl $CURLOPTS -c $COOKIEJAR -F "cmd=login" -F "login=admin" -F "passwd=admin" "http://localhost/domjudge/jury/"

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
