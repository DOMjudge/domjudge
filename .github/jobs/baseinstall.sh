#!/bin/bash

. .github/jobs/ci_settings.sh

export version="$1"
db=${2:-install}
phpversion="${3:-8.1}"
# If this script is called from unit-tests.sh, we use the test environment
export APP_ENV="${4:-prod}"

# In the test environment, we need to use a different database
[ "$APP_ENV" = "prod" ] && DATABASE_NAME=domjudge || DATABASE_NAME=domjudge_test

MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD:-root}

set -euxo pipefail

if [ -z "$phpversion" ]; then
phpversion=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION."\n";')
fi

show_phpinfo "$phpversion"

section_start "Run composer"
export APP_ENV="dev"
cd webapp
composer install --no-scripts |tee "$ARTIFACTS"/composer_out.txt
cd ..
section_end

section_start "Set simple admin password"
echo "password" > ./etc/initial_admin_password.secret
echo "default login admin password password" > ~/.netrc
section_end

section_start "Install domserver"
make configure
if [ "$version" = "all" ]; then
    # Note that we use http instead of https here as python requests doesn't
    # like our self-signed cert. We should fix this separately.
    ./configure \
      --with-baseurl='http://localhost/domjudge/' \
      --with-domjudge-user=domjudge \
      --with-judgehost-chrootdir=/chroot/domjudge | tee "$ARTIFACTS"/configure.txt
    make build-scripts domserver judgehost docs
    make install-domserver install-judgehost install-docs
else
    ./configure \
      --with-baseurl='https://localhost/domjudge/' \
      --with-domjudge-user=root \
      --enable-doc-build=no \
      --enable-judgehost-build=no | tee "$ARTIFACTS"/configure.txt
    make domserver
    make install-domserver
    rm -rf /opt/domjudge/domserver/webapp/public/doc
    cp -r doc /opt/domjudge/domserver/webapp/public/
    find /opt/domjudge/domserver -name DOMjudgelogo.pdf
fi
section_end

section_start "SQL settings"
cat > ~/.my.cnf <<EOF
[client]
host=sqlserver
user=root
password=${MYSQL_ROOT_PASSWORD}
EOF
cat ~/.my.cnf

mysql_root "CREATE DATABASE IF NOT EXISTS \`$DATABASE_NAME\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql_root "CREATE USER IF NOT EXISTS \`domjudge\`@'%' IDENTIFIED BY 'domjudge';"
mysql_root "GRANT SELECT, INSERT, UPDATE, DELETE ON \`$DATABASE_NAME\`.* TO 'domjudge'@'%';"
mysql_root "FLUSH PRIVILEGES;"

# Show some MySQL debugging
mysql_root "show databases"
mysql_root "SELECT CURRENT_USER();"
mysql_root "SELECT USER();"
mysql_root "SELECT user,host FROM mysql.user"
mysql_root "SET GLOBAL max_allowed_packet=1073741824"
mysql_root "SHOW GLOBAL STATUS LIKE 'Connection_errors_%'"
mysql_root "SHOW VARIABLES LIKE '%_timeout'"
echo "unused:sqlserver:$DATABASE_NAME:domjudge:domjudge:3306" > /opt/domjudge/domserver/etc/dbpasswords.secret
mysql_user "SELECT CURRENT_USER();"
mysql_user "SELECT USER();"
section_end

if [ "${db}" = "install" ]; then
    section_start "Install DOMjudge database"
    /opt/domjudge/domserver/bin/dj_setup_database -uroot -p${MYSQL_ROOT_PASSWORD} bare-install
    section_end
elif [ "${db}" = "upgrade" ]; then
    section_start "Upgrade DOMjudge database"
    /opt/domjudge/domserver/bin/dj_setup_database -uroot -p${MYSQL_ROOT_PASSWORD} upgrade
    section_end
fi

section_start "Show PHP config"
php -v | tee -a "$ARTIFACTS"/php.txt
php -m | tee -a "$ARTIFACTS"/php.txt
section_end

section_start "Show general config"
printenv | tee -a "$ARTIFACTS"/environment.txt
cp /etc/os-release "$ARTIFACTS"/os-release.txt
cp /proc/cmdline "$ARTIFACTS"/cmdline.txt
section_end

section_start "Setup webserver"
cp /opt/domjudge/domserver/etc/domjudge-fpm.conf /etc/php/"$phpversion"/fpm/pool.d/domjudge.conf

rm -f /etc/nginx/sites-enabled/*
cp /opt/domjudge/domserver/etc/nginx-conf /etc/nginx/sites-enabled/domjudge

openssl req -nodes -new -x509 -keyout /tmp/server.key -out /tmp/server.crt -subj "/C=NL/ST=Noord-Holland/L=Amsterdam/O=TestingForPR/CN=localhost"
cp /tmp/server.crt /usr/local/share/ca-certificates/
update-ca-certificates
# shellcheck disable=SC2002
cat "$(pwd)/.github/jobs/data/nginx_extra" | tee -a /etc/nginx/sites-enabled/domjudge
nginx -t
section_end

section_start "Show webserver is up"
for service in nginx php${phpversion}-fpm; do
    service "$service" restart
    service "$service" status
done
section_end

if [ "${db}" = "install" ]; then
    section_start "Install the example data"
    if [ "$version" = "unit" ]; then
	    # Make sure admin has no team associated so we will not insert submissions during unit tests.
	    mysql_root "UPDATE user SET teamid=null WHERE userid=1;" $DATABASE_NAME
    fi
    /opt/domjudge/domserver/bin/dj_setup_database -uroot -p${MYSQL_ROOT_PASSWORD} install-examples | tee -a "$ARTIFACTS/mysql.txt"
    section_end
fi

section_start "Setup user"
# We're using the admin user in all possible roles
mysql_root "DELETE FROM userrole WHERE userid=1;" $DATABASE_NAME
if [ "$version" = "team" ]; then
    # Add team to admin user
    mysql_root "INSERT INTO userrole (userid, roleid) VALUES (1, 3);" $DATABASE_NAME
    mysql_root "UPDATE user SET teamid = 1 WHERE userid = 1;" $DATABASE_NAME
elif [ "$version" = "jury" ]; then
    # Add jury to admin user
    mysql_root "INSERT INTO userrole (userid, roleid) VALUES (1, 2);" $DATABASE_NAME
elif [ "$version" = "balloon" ]; then
    # Add balloon to admin user
    mysql_root "INSERT INTO userrole (userid, roleid) VALUES (1, 4);" $DATABASE_NAME
elif [ "$version" = "admin" ]; then
    # Add admin to admin user
    mysql_root "INSERT INTO userrole (userid, roleid) VALUES (1, 1);" $DATABASE_NAME
elif [ "$version" = "all" ] || [ "$version" = "unit" ]; then
    mysql_root "INSERT INTO userrole (userid, roleid) VALUES (1, 1);" $DATABASE_NAME
    mysql_root "INSERT INTO userrole (userid, roleid) VALUES (1, 3);" $DATABASE_NAME
    mysql_root "UPDATE user SET teamid = 1 WHERE userid = 1;" $DATABASE_NAME
fi
section_end
