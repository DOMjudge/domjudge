#!/bin/bash

set -euxo pipefail

. gitlab/dind.profile

# Set up
"$( dirname "${BASH_SOURCE[0]}" )"/base.sh

# Add team to admin user
echo "INSERT INTO userrole (userid, roleid) VALUES (1, 3);" | mysql domjudge
echo "UPDATE user SET teamid = 1 WHERE userid = 1;" | mysql domjudge

# Copy the .env.test file, as this is normally not done during
# installation and we need it.
cp webapp/.env.test /opt/domjudge/domserver/webapp/

# We also need the composer.json for PHPunit to detect the correct directory.
cp composer.json /opt/domjudge/domserver/

DIR=$(pwd)
cd /opt/domjudge/domserver

export APP_ENV="test"

# Run phpunit tests.
set +e
php -dpcov.enabled=1 -dpcov.directory=webapp/src lib/vendor/bin/phpunit -c webapp/phpunit.xml.dist --log-junit ${CI_PROJECT_DIR}/unit-tests.xml --colors=never --coverage-html=${CI_PROJECT_DIR}/coverage-html --coverage-clover coverage.xml > phpunit.out
UNITSUCCESS=$?
set -e
CNT=$(sed -n '/Generating code coverage report/,$p' phpunit.out | grep -v DoctrineTestBundle | grep -v ^$ | wc -l)
FILE=deprecation.txt
sed -n '/Generating code coverage report/,$p' phpunit.out > ${CI_PROJECT_DIR}/$FILE
if [ $CNT -lt 5 ]; then
    STATE=success
else
    STATE=failure
fi
ORIGINAL="gitlab.com/DOMjudge"
REPLACETO="domjudge.gitlab.io/-"
# Copied from CCS
curl https://api.github.com/repos/domjudge/domjudge/statuses/$CI_COMMIT_SHA \
  -X POST \
  -H "Authorization: token $GH_BOT_TOKEN_OBSCURED" \
  -H "Accept: application/vnd.github.v3+json" \
  -d "{\"state\": \"$STATE\", \"target_url\": \"${CI_JOB_URL/$ORIGINAL/$REPLACETO}/artifacts/$FILE\", \"description\":\"Symfony deprecations\", \"context\": \"Symfony deprecation\"}"
if [ $UNITSUCCESS -eq 0 ]; then
    STATE=success
else
    STATE=failure
fi
curl https://api.github.com/repos/domjudge/domjudge/statuses/$CI_COMMIT_SHA \
    -X POST \
    -H "Authorization: token $GH_BOT_TOKEN_OBSCURED" \
    -H "Accept: application/vnd.github.v3+json" \
    -d "{\"state\": \"$STATE\", \"target_url\": \"${CI_PIPELINE_URL}/test_report\", \"description\":\"Unit tests\", \"context\": \"unit_tests\"}"
if [ $UNITSUCCESS -ne 0 ]; then
    exit 1
fi

section_start_collap uploadcoverage "Upload code coverage"
# Only upload when we got working unit-tests.
set +u # Uses some variables which are not set
. $DIR/gitlab/uploadcodecov.sh 1>/dev/zero 2>/dev/zero
section_end uploadcoverage
