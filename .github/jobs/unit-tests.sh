#!/bin/bash

. .github/jobs/ci_settings.sh

set -euxo pipefail

DIR="$PWD"

export version=$1
unittest=$2

# Set up
export unit=1

# Add team to admin user
mysql_log "UPDATE user SET teamid = 1 WHERE userid = 1;" domjudge_test

# Copy the .env.test file, as this is normally not done during
# installation and we need it.
cp webapp/.env.test /opt/domjudge/domserver/webapp/

# We also need the composer.json for PHPunit to detect the correct directory.
cp webapp/composer.json /opt/domjudge/domserver/webapp/

cd /opt/domjudge/domserver

# The tests add a '_test' suffix to the database name already.
sed -i "s!:domjudge_test:!:domjudge:!" /opt/domjudge/domserver/etc/dbpasswords.secret

# Run phpunit tests.
set +e
php webapp/bin/phpunit -c webapp/phpunit.xml.dist webapp/tests/$unittest --log-junit ${ARTIFACTS}/unit-tests.xml --colors=never | tee "$ARTIFACTS"/phpunit.out
UNITSUCCESS=$?

# Store the unit tests also in the root for the GHA
cp $ARTIFACTS/unit-tests.xml $DIR/unit-tests-${version}-${unittest}.xml

# Make sure the log exists before copy
touch ${DIR}/webapp/var/log/test.log
cp ${DIR}/webapp/var/log/*.log "$ARTIFACTS"/

set -e

if [ $UNITSUCCESS -ne 0 ]; then
    exit 1
fi
