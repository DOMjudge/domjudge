#!/bin/bash

set -euxo pipefail

# Set up
"$( dirname "${BASH_SOURCE[0]}" )"/base.sh

cd /opt/domjudge/domserver

# run phpunit tests
lib/vendor/bin/phpunit -c webapp/phpunit.xml.dist --log-junit ${CI_PROJECT_DIR}/unit-tests.xml --coverage-text --colors=never || true
