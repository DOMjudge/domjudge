#!/bin/bash

echo $@
echo $0
echo $1
echo $2
echo $3
echo $4

export MYSQL_ROOT_PASSWORD="$4"

echo $MYSQL_ROOT_PASSWORD

. .github/jobs/data/gha_ci_bashrc

section_start chown_checkout
git config --global --add safe.directory /__w/domjudge/domjudge
section_end chown_checkout

section_start install_needed_sqlclient_tools
if [ $3 = "mysql" ]; then
    apt-get purge mariadb-client -y
    apt-get update
    apt-get install mysql-client -y
fi
section_end install_needed_sqlclient_tools

export version=$1
unittest=$2
[ "$version" = "8.1" ] && CODECOVERAGE=1 || CODECOVERAGE=0

show_phpinfo $version

.github/jobs/base.sh

# Add team to admin user
echo "INSERT INTO userrole (userid, roleid) VALUES (1, 3);" | mysql domjudge
echo "UPDATE user SET teamid = 1 WHERE userid = 1;" | mysql domjudge

# Copy the .env.test file, as this is normally not done during
# installation and we need it.
cp webapp/.env.test /opt/domjudge/domserver/webapp/

# We also need the composer.json for PHPunit to detect the correct directory.
cp composer.json /opt/domjudge/domserver/

cd /opt/domjudge/domserver

export APP_ENV="test"

# Run phpunit tests.
pcov=""
phpcov=""
if [ "$CODECOVERAGE" -eq 1 ]; then
    phpcov="-dpcov.enabled=1 -dpcov.directory=webapp/src"
    pcov="--coverage-html=${PWD}/coverage-html --coverage-clover coverage.xml"
fi
set +e
php $phpcov lib/vendor/bin/phpunit -c webapp/phpunit.xml.dist webapp/tests/$unittest --log-junit ${DIR}/unit-tests.xml --colors=never $pcov > "$ARTIFACTS"/phpunit.out
UNITSUCCESS=$?
set -e
CNT=0
if [ $CODECOVERAGE -eq 1 ]; then
    CNT=$(sed -n '/Generating code coverage report/,$p' "$ARTIFACTS"/phpunit.out | grep -v DoctrineTestBundle | grep -cv ^$)
    FILE=deprecation.txt
    sed -n '/Generating code coverage report/,$p' "$ARTIFACTS"/phpunit.out > "$DIR/$FILE"
    if [ $CNT -le 12 ]; then
        STATE=success
    else
        STATE=failure
    fi
fi
if [ $UNITSUCCESS -ne 0 ]; then
    exit $UNITSUCCESS
fi

if [ $CODECOVERAGE -eq 1 ]; then
    section_start uploadcoverage "Upload code coverage"
    # Only upload when we got working unit-tests.
    set +u # Uses some variables which are not set
    # shellcheck disable=SC1090
    . $DIR/.github/jobs/uploadcodecov.sh &>/dev/zero
    set -u # Undo set dance
    section_end uploadcoverage
fi
