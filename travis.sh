#!/bin/bash -ex

DIR=$(pwd)
lsb_release -a

# install composer(per the composer docs)
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('SHA384', 'composer-setup.php') === '070854512ef404f16bac87071a6db9fd9721da1684cd4589b1196c3faf71b9a2682e2311b36a5079825e155ac7ce150d') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php --filename=composer
php -r "unlink('composer-setup.php');"

# install all php dependencies
./composer install --no-dev

# downgrade java version outside of chroot since this didn't work
sudo apt-get remove -y openjdk-8-jdk openjdk-8-jre openjdk-8-jre-headless oracle-java7-installer oracle-java8-installer

# configure, make and install (but skip documentation)
make configure
./configure --disable-doc-build
make domserver judgehost
sudo make install-domserver install-judgehost

# setup database and add special user
cd /opt/domjudge/domserver
sudo bin/dj_setup_database install
echo "INSERT INTO user (userid, username, name, password, teamid) VALUES (3, 'dummy', 'dummy user for example team', MD5('dummy#dummy'), 2)" | sudo mysql domjudge
echo "INSERT INTO userrole (userid, roleid) VALUES (3, 2);" | sudo mysql domjudge
echo "INSERT INTO userrole (userid, roleid) VALUES (3, 3);" | sudo mysql domjudge
echo "machine localhost login dummy password dummy" > ~/.netrc

# configure and restart apache
sudo cp /opt/domjudge/domserver/etc/apache.conf /etc/apache2/sites-enabled/
sudo service apache2 restart

# add users judgedaemons (FIXME: make them configurable)
sudo useradd -d /nonexistent -g nogroup -s /bin/false domjudge-run-0
sudo useradd -d /nonexistent -g nogroup -s /bin/false domjudge-run-1

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

# include debug output here
if [ $NUMNOTVERIFIED -ne 1 ]; then
	echo "only 1 submission is expected to be unverified, but $NUMNOTVERIFIED are"
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
