#!/bin/bash

set -euxo pipefail

export PS4='(${BASH_SOURCE}:${LINENO}): - [$?] $ '

gitlabartifacts="$(pwd)/gitlabartifacts"
mkdir -p "$gitlabartifacts"

DIR=$(pwd)
lsb_release -a

GITSHA=$(git rev-parse HEAD || true)

cat > ~/.my.cnf <<EOF
[client]
host=${MARIADB_PORT_3306_TCP_ADDR}
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

# setup database and add special user
cd /opt/domjudge/domserver

# Generate a dbpasswords file
echo "unused:${MARIADB_PORT_3306_TCP_ADDR}:domjudge:domjudge:domjudge:3306" > etc/dbpasswords.secret

ls -atrl /opt/domjudge/domserver/etc

setfacl -m u:www-data:r etc/restapi.secret etc/initial_admin_password.secret etc/dbpasswords.secret etc/symfony_app.secret

sudo -u www-data bin/dj_setup_database -uroot -p${MYSQL_ROOT_PASSWORD} -q install
