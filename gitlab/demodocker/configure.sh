#!/bin/sh -eu

composer install --no-scripts
composer run-script package-versions-dump

# configure, make and install (but skip documentation)
make configure
./configure --with-baseurl='http://localhost/domjudge/' --with-domjudge-user=domjudge
make build-scripts domserver judgehost
sudo make install-domserver install-judgehost

cat > ~/.my.cnf <<EOF
[client]
host=sqlserver
user=root
password=password
EOF
cat ~/.my.cnf

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
EOF

# setup database and add special user
cd /opt/domjudge/domserver
setfacl -m u:www-data:r etc/restapi.secret etc/initial_admin_password.secret \
                        etc/dbpasswords.secret etc/symfony_app.secret

# configure and restart nginx
sudo rm -f /etc/nginx/sites-enabled/*
sudo cp /opt/domjudge/domserver/etc/nginx-conf /etc/nginx/sites-enabled/domjudge
sudo /usr/sbin/nginx

service nginx enable
service php7.4-fpm enable
