#!/bin/bash -ex

# Set up
"$( dirname "${BASH_SOURCE[0]}" )"/base.sh

cd /opt/domjudge/domserver

# run phpunit tests
lib/vendor/bin/phpunit --stderr -c webapp/phpunit.xml.dist --log-junit ${CI_PROJECT_DIR}/unit-tests.xml
