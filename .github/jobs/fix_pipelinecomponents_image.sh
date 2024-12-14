#!/bin/sh

set -eux

echo "Set plugin config for version detection"
phpcs --config-set installed_paths /app/vendor/phpcompatibility/php-compatibility
      
# Current released version does not know enums
echo "Upgrade the compatibility for PHP versions"
mydir=$(pwd)
sed -i 's/"phpcompatibility\/php-compatibility": "9.3.5"/"phpcompatibility\/php-compatibility": "dev-develop"/g' /app/composer.json
cd /app; composer update
cd $mydir
