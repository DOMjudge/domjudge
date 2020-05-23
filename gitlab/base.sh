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

# Increase max_allowed_packet for following connections.
echo "SET GLOBAL max_allowed_packet = 100*1024*1024;" | mysql

# Generate a dbpasswords file
echo "dummy:${MARIADB_PORT_3306_TCP_ADDR}:domjudge:domjudge:domjudge" > etc/dbpasswords.secret

# Generate APP_SECRET for symfony
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
    domjudge.submitclient_enabled: yes
EOF

# install all php dependencies
export APP_ENV="prod"
composer install --no-scripts
composer run-script package-versions-dump
echo -e "\033[0m"

# configure, make and install (but skip documentation)
make configure
./configure --with-baseurl='http://localhost/domjudge/' --with-domjudge-user=domjudge --with-judgehost_chrootdir=${DIR}/chroot/domjudge
make build-scripts domserver judgehost docs
sudo make install-domserver install-judgehost install-docs

# setup database and add special user
cd /opt/domjudge/domserver
setfacl -m u:www-data:r etc/restapi.secret etc/initial_admin_password.secret \
                        etc/dbpasswords.secret etc/symfony_app.secret
sudo -u www-data bin/dj_setup_database -uroot -p${MYSQL_ROOT_PASSWORD} -q install

# configure and restart nginx
sudo rm -f /etc/nginx/sites-enabled/*
sudo cp /opt/domjudge/domserver/etc/nginx-conf /etc/nginx/sites-enabled/domjudge
sudo /usr/sbin/nginx
