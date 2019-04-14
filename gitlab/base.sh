#!/bin/bash

set -euxo pipefail

export PS4='(${BASH_SOURCE}:${LINENO}): - [$?] $ '

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
echo "GRANT SELECT, INSERT, UPDATE, DELETE ON \`domjudge\`.* TO 'domjudge'@'%' IDENTIFIED BY 'domjudge';" | mysql

# Generate a dbpasswords file
echo "dummy:${MARIADB_PORT_3306_TCP_ADDR}:domjudge:domjudge:domjudge" > etc/dbpasswords.secret

# Generate a parameters yml file for symfony
cat > webapp/app/config/parameters.yml <<EOF
parameters:
    database_host: ${MARIADB_PORT_3306_TCP_ADDR}
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

    # Needs a version number
    domjudge.version: 0.0.dummy
    domjudge.tmpdir: /tmp
EOF

cat > webapp/app/config/static.yml <<EOF
parameters:
    domjudge.version: unconfigured
    domjudge.bindir: /bin
    domjudge.etcdir: /etc
    domjudge.wwwdir: /www
    domjudge.webappdir: /webapp
    domjudge.libdir: /lib
    domjudge.sqldir: /sql
    domjudge.libvendordir: /lib/vendor
    domjudge.libwwwdir: /lib/www
    domjudge.libsubmitdir: /lib/submit
    domjudge.logdir: /output/log
    domjudge.rundir: /output/run
    domjudge.tmpdir: /output/tmp
    domjudge.submitdir: /output/submissions
    domjudge.baseurl: http://localhost/domjudge
    domjudge.submitclient_enabled: yes
EOF

# install all php dependencies
export SYMFONY_ENV="prod"
composer install --no-scripts

# configure, make and install (but skip documentation)
make configure
./configure --disable-doc-build --with-baseurl='http://localhost/domjudge/' --with-domjudge-user=domjudge
make build-scripts domserver judgehost
sudo make install-domserver install-judgehost

# setup database and add special user
cd /opt/domjudge/domserver
sudo bin/dj_setup_database install
ADMINPASS=$(cat etc/initial_admin_password.secret)
echo "INSERT INTO user (userid, username, name, password, teamid) VALUES (3, 'dummy', 'dummy user for example team', '\$2y\$10\$0d0sPmeAYTJ/Ya7rvA.kk.zvHu758ScyuHAjps0A6n9nm3eFmxW2K', 2)" | mysql domjudge
echo "INSERT INTO userrole (userid, roleid) VALUES (3, 2);" | mysql domjudge
echo "INSERT INTO userrole (userid, roleid) VALUES (3, 3);" | mysql domjudge
echo "machine localhost login dummy password dummy" > ~/.netrc

# configure and restart nginx
sudo rm -f /etc/nginx/sites-enabled/*
sudo cp /opt/domjudge/domserver/etc/nginx-conf /etc/nginx/sites-enabled/domjudge
sudo /usr/sbin/nginx
