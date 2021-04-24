#!/bin/bash

set -euxo pipefail

# Set up
"$( dirname "${BASH_SOURCE[0]}" )"/base.sh

# Copy the .env.test file, as this is normally not done during
# installation and we need it.
cp webapp/.env.test /opt/domjudge/domserver/webapp/

# We also need the composer.json for PHPunit to detect the correct directory.
cp composer.json /opt/domjudge/domserver/

DIR=$(pwd)
cd /opt/domjudge/domserver

export APP_ENV="test"

# Run phpunit tests.
php -dpcov.enabled=1 -dpcov.directory=webapp/src lib/vendor/bin/phpunit -c webapp/phpunit.xml.dist --log-junit ${CI_PROJECT_DIR}/unit-tests.xml --colors=never --coverage-html=${CI_PROJECT_DIR}/coverage-html --coverage-clover coverage.xml > phpunit.out
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
cd $DIR
set +u # Uses some variables which are not set
. gitlab/uploadcodecov.sh
