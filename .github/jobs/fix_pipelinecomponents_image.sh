#!/bin/sh

# We can't convert this script to bash as GHA doesn't have bash installed
#
# The current script doesn't use a piped command so the "benefit" of using
# pipefail is not there over the hassle of maintaining another container.
set -eux

echo "Set plugin config for version detection"
phpcs --config-set installed_paths /app/vendor/phpcompatibility/php-compatibility

# Current released version does not know enums
echo "Upgrade the compatibility for PHP versions"
mydir=$(pwd)

echo "Before /app/composer.json:"
cat /app/composer.json

echo "Install the only needed tool"
rm /app/composer.json
cd /app
composer require phpcompatibility/php-compatibility:dev-develop
composer config --no-plugins allow-plugins.dealerdirect/phpcodesniffer-composer-installer

echo "After /app/composer.json:"
cat /app/composer.json

composer update
cd $mydir
