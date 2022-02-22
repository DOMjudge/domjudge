#!/bin/sh

set -eux

sudo apt update
sudo apt install -y acl zip unzip nginx php php-fpm php-gd \
                    php-cli php-intl php-mbstring php-mysql php-curl php-json \
                    php-xml php-zip ntp make sudo debootstrap \
                    libcgroup-dev lsof php-cli php-curl php-json php-xml \
                    php-zip procps gcc g++ default-jre-headless \
                    default-jdk-headless ghc fp-compiler autoconf automake bats \
                    python3-sphinx python3-sphinx-rtd-theme rst2pdf fontconfig \
                    python3-yaml latexmk curl
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
HASH="$(wget -q -O - https://composer.github.io/installer.sig)"
php -r "if (hash_file('SHA384', 'composer-setup.php') === '$HASH') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer

composer install --no-scripts

sudo sed -i "s/\$operation->operationId =/#\$operation->operationId =/g" lib/vendor/nelmio/api-doc-bundle/OpenApiPhp/DefaultOperationId.php

DIR=$(pwd)
make configure
./configure --with-baseurl='https://localhost/domjudge/' --enable-doc-build=no --prefix=$HOME/domjudge

make domserver
sudo make install-domserver

sudo /etc/init.d/mysql start

$HOME/domjudge/domserver/bin/dj_setup_database -uroot -proot install

sudo cp $HOME/domjudge/domserver/etc/domjudge-fpm.conf /etc/php/7.4/fpm/pool.d/domjudge.conf
sudo systemctl restart php7.4-fpm
sudo systemctl status php7.4-fpm

sudo rm -f /etc/nginx/sites-enabled/*
sudo cp $HOME/domjudge/domserver/etc/nginx-conf /etc/nginx/sites-enabled/domjudge

openssl req -nodes -new -x509 -keyout /tmp/server.key -out /tmp/server.crt -subj "/C=NL/ST=Noord-Holland/L=Amsterdam/O=TestingForPR/CN=localhost"
# shellcheck disable=SC2002
cat $(pwd)/.github/workflowscripts/nginx_extra | sudo tee -a /etc/nginx/sites-enabled/domjudge

sudo systemctl restart nginx
