#!/bin/bash

. gitlab/ci_settings.sh

lsb_release -a

cat > ~/.my.cnf <<EOF
[client]
host=sqlserver
user=root
password=${MYSQL_ROOT_PASSWORD}
EOF
cat ~/.my.cnf

# FIXME: This chicken-egg problem is annoying but let us bootstrap for now.
echo "CREATE DATABASE IF NOT EXISTS \`domjudge\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | mysql
echo "CREATE USER 'domjudge'@'%' IDENTIFIED BY 'domjudge';" | mysql
echo "GRANT SELECT, INSERT, UPDATE, DELETE ON \`domjudge\`.* TO 'domjudge'@'%';" | mysql

# Increase max_allowed_packet for following connections.
echo "SET GLOBAL max_allowed_packet = 100*1024*1024;" | mysql

# Test that SQL upgrade scripts also work with this setting
if [ -n "${MYSQL_REQUIRE_PRIMARY_KEY:-}" ]; then
    echo 'SET GLOBAL sql_require_primary_key = 1;' | mysql
fi

# Generate a dbpasswords file
echo "unused:sqlserver:domjudge:domjudge:domjudge:3306" > etc/dbpasswords.secret

# Generate APP_SECRET for symfony
# shellcheck disable=SC2164
( cd etc ; ./gensymfonysecret > symfony_app.secret )

cat > webapp/config/static.yaml <<EOF
parameters:
    domjudge.version: unconfigured
    domjudge.bindir: /bin
    domjudge.etcdir: /etc
    domjudge.wwwdir: /www
    domjudge.webappdir: /webapp
    domjudge.libdir: /lib
    domjudge.sqldir: /sql
    domjudge.libvendordir: /lib/vendor
    domjudge.logdir: /output/log
    domjudge.rundir: /output/run
    domjudge.tmpdir: /output/tmp
    domjudge.baseurl: http://localhost/domjudge
EOF

# install all php dependencies
export APP_ENV="prod"
composer install --no-scripts
echo -e "\033[0m"

# configure, make and install (but skip documentation)
make configure
./configure --with-baseurl='http://localhost/domjudge/' --with-domjudge-user=domjudge --with-judgehost_chrootdir=${DIR}/chroot/domjudge |& tee "$GITLABARTIFACTS/configure.log"
make build-scripts domserver judgehost docs |& tee "$GITLABARTIFACTS/make.log"
sudo make install-domserver install-judgehost install-docs |& tee -a "$GITLABARTIFACTS/make.log"

# setup database and add special user
# shellcheck disable=SC2164
cd /opt/domjudge/domserver
setfacl -m u:www-data:r etc/restapi.secret etc/initial_admin_password.secret \
                        etc/dbpasswords.secret etc/symfony_app.secret

# configure and restart nginx
sudo rm -f /etc/nginx/sites-enabled/*
sudo cp /opt/domjudge/domserver/etc/nginx-conf /etc/nginx/sites-enabled/domjudge
sudo /usr/sbin/nginx

# configure and restart php-fpm
# shellcheck disable=SC2154
php_version="${version:-}"
sudo cp /opt/domjudge/domserver/etc/domjudge-fpm.conf "/etc/php/$php_version/fpm/pool.d/domjudge-fpm.conf"
echo "php_admin_value[date.timezone] = Europe/Amsterdam" | sudo tee -a "/etc/php/$php_version/fpm/pool.d/domjudge-fpm.conf"
sudo /usr/sbin/php-fpm${php_version}
echo "date.timezone = Europe/Amsterdam" | sudo tee -a "/etc/php/$php_version/cli/php.ini"

passwd=$(cat etc/initial_admin_password.secret)
echo "machine localhost login admin password $passwd" >> ~www-data/.netrc
sudo -u www-data bin/dj_setup_database -uroot -p${MYSQL_ROOT_PASSWORD} bare-install

# shellcheck disable=SC2154
if [ -n "${integration:-}" ]; then
	# Make sure admin has a team associated to insert submissions as well.
	echo "UPDATE user SET teamid=1 WHERE userid=1;" | mysql domjudge
fi

sudo -u www-data bin/dj_setup_database -uroot -p${MYSQL_ROOT_PASSWORD} install-examples
