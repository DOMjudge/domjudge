#!/bin/bash

. .github/jobs/ci_settings.sh

set -euxo pipefail

DIR="$PWD"

export version=$1
unittest=$2
[ "$version" = "8.1" ] && CODECOVERAGE=1 || CODECOVERAGE=0

# Set up
export unit=1

# Add team to admin user
echo "UPDATE user SET teamid = 1 WHERE userid = 1;" | mysql domjudge_test

# Copy the .env.test file, as this is normally not done during
# installation and we need it.
cp webapp/.env.test /opt/domjudge/domserver/webapp/

# We also need the composer.json for PHPunit to detect the correct directory.
cp webapp/composer.json /opt/domjudge/domserver/webapp/

cd /opt/domjudge/domserver

# Run phpunit tests.
pcov=""
phpcov=""
if [ "$CODECOVERAGE" -eq 1 ]; then
    phpcov="-dpcov.enabled=1 -dpcov.directory=webapp/src"
    pcov="--coverage-html=${DIR}/coverage-html --coverage-clover coverage.xml"
fi
set +e
echo "unused:sqlserver:domjudge:domjudge:domjudge:3306" > /opt/domjudge/domserver/etc/dbpasswords.secret
php $phpcov webapp/bin/phpunit -c webapp/phpunit.xml.dist webapp/tests/$unittest --log-junit ${ARTIFACTS}/unit-tests.xml --colors=never $pcov | tee "$ARTIFACTS"/phpunit.out
UNITSUCCESS=$?

# Store the unit tests also in the root for the GHA
cp $ARTIFACTS/unit-tests.xml $DIR/unit-tests-${version}-${unittest}.xml

# Make sure the log exists before copy
touch ${DIR}/webapp/var/log/test.log
cp ${DIR}/webapp/var/log/*.log "$ARTIFACTS"/

set -e
CNT=0
THRESHOLD=2
if [ $CODECOVERAGE -eq 1 ]; then
    CNT=$(sed -n '/Generating code coverage report/,$p' "$ARTIFACTS"/phpunit.out | grep -cv ^$)
fi

if [ $UNITSUCCESS -ne 0 ] || [ $CNT -gt $THRESHOLD ]; then
    exit 1
fi

if [ $CODECOVERAGE -eq 1 ]; then
    section_start "Upload code coverage"
    # Only upload when we got working unit-tests.
    set +u # Uses some variables which are not set
    # shellcheck disable=SC1090
    . $DIR/.github/jobs/uploadcodecov.sh &>> "$ARTIFACTS"/codecov.log
    section_end
fi
