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

if [ "$unittest" = "Unit" ]; then
    # Run the tests in parallel with ParaTest. Every worker uses its own
    # database domjudge_test<TEST_TOKEN>, so clone the test database.
    PROCS=$(nproc)
    mysqldump --quick --max_allowed_packet=1024M domjudge_test > /tmp/domjudge_test.sql
    for i in $(seq 1 "$PROCS"); do
        mysql_log "CREATE DATABASE domjudge_test$i;"
        mysql_log "GRANT SELECT, INSERT, UPDATE, DELETE ON domjudge_test$i.* TO 'domjudge'@'%';"
        mysql --max_allowed_packet=1024M "domjudge_test$i" < /tmp/domjudge_test.sql
    done

    # Tests in the 'no-parallel' group modify files shared by all workers,
    # so run them afterwards on their own.
    set +e
    php webapp/vendor/bin/paratest -p "$PROCS" -c webapp/phpunit.xml.dist webapp/tests/$unittest --exclude-group no-parallel --log-junit ${ARTIFACTS}/unit-tests.xml --colors=never | tee "$ARTIFACTS"/phpunit.out
    UNITSUCCESS=$?
    php webapp/bin/phpunit -c webapp/phpunit.xml.dist webapp/tests/$unittest --group no-parallel --log-junit ${ARTIFACTS}/unit-tests-no-parallel.xml --colors=never | tee -a "$ARTIFACTS"/phpunit.out
    SERIALSUCCESS=$?
    cp $ARTIFACTS/unit-tests-no-parallel.xml $DIR/unit-tests-${version}-${unittest}-no-parallel.xml
else
    # Run phpunit tests.
    set +e
    php webapp/bin/phpunit -c webapp/phpunit.xml.dist webapp/tests/$unittest --log-junit ${ARTIFACTS}/unit-tests.xml --colors=never | tee "$ARTIFACTS"/phpunit.out
    UNITSUCCESS=$?
    SERIALSUCCESS=0
fi

# Store the unit tests also in the root for the GHA
cp $ARTIFACTS/unit-tests.xml $DIR/unit-tests-${version}-${unittest}.xml

# Make sure the log exists before copy
touch ${DIR}/webapp/var/log/test.log
cp ${DIR}/webapp/var/log/*.log "$ARTIFACTS"/

set -e

if [ $UNITSUCCESS -ne 0 ] || [ $SERIALSUCCESS -ne 0 ]; then
    exit 1
fi
