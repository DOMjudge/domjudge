#!/bin/sh -eu

set -eux

cd /opt/domjudge/domserver/
setfacl -m u:www-data:r etc/restapi.secret etc/initial_admin_password.secret \
                        etc/dbpasswords.secret etc/symfony_app.secret

ls -atrl etc
ls -Z etc

chmod a+r etc/restapi.secret etc/initial_admin_password.secret \
          etc/dbpasswords.secret etc/symfony_app.secret

ls -atrl etc
ls -Z etc

# Install the DB in both SQL servers
for SQLSERVER in mariadb mysql; do
    echo "dummy:${SQLSERVER}:domjudge:domjudge:domjudge" > etc/dbpasswords.secret
    ls -atrl etc
    ls -Z etc
    sudo -u www-data bin/dj_setup_database -uroot -p${MYSQL_ROOT_PASSWORD} -q install
done
