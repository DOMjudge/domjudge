#!/bin/bash

set -euxo pipefail

# Set up
"$( dirname "${BASH_SOURCE[0]}" )"/base.sh

# Copy the .env.test file, as this is normally not done during
# installation and we need it.
cp webapp/.env.test /opt/domjudge/domserver/webapp/

# We also need the composer.json for PHPunit to detect the correct directory.
cp composer.json /opt/domjudge/domserver/

cd /opt/domjudge/domserver

export APP_ENV="test"

# Run phpunit tests.
lib/vendor/bin/phpunit -c webapp/phpunit.xml.dist --log-junit ${CI_PROJECT_DIR}/unit-tests.xml --colors=never
