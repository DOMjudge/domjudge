#!/bin/sh

# Functions to annotate the Github actions logs
alias trace_on='set -x'
alias trace_off='{ set +x; } 2>/dev/null'

section_start_internal  () {
    echo "::group::$1"
    trace_on
}

section_end_internal () {
    echo "::endgroup::"
    trace_on
}

alias section_start='trace_off ; section_start_internal '
alias section_end='trace_off ; section_end_internal '

set -eux

section_start "Update packages"
sudo apt update
section_end

section_start "Install needed packages"
sudo apt install -y acl zip unzip nginx php php-fpm php-gd \
                    php-cli php-intl php-mbstring php-mysql php-curl php-json \
                    php-xml php-zip ntp make sudo debootstrap \
                    libcgroup-dev lsof php-cli php-curl php-json php-xml \
                    php-zip procps gcc g++ default-jre-headless \
                    default-jdk-headless ghc fp-compiler autoconf automake bats \
                    python3-sphinx python3-sphinx-rtd-theme rst2pdf fontconfig \
                    python3-yaml latexmk curl
section_end

section_start "Install composer"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
HASH="$(wget -q -O - https://composer.github.io/installer.sig)"
php -r "if (hash_file('SHA384', 'composer-setup.php') === '$HASH') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
section_end

section_start "Run composer"
export APP_ENV="dev"
composer install --no-scripts
section_end

section_start "Install domserver"
make configure
./configure --with-baseurl='https://localhost/domjudge/' --enable-doc-build=no --prefix="/opt/domjudge"

make domserver
sudo make install-domserver
section_end

section_start "Explicit start mysql + install DB"
sudo /etc/init.d/mysql start

/opt/domjudge/domserver/bin/dj_setup_database -uroot -proot install
section_end

section_start "Setup webserver"
sudo cp /opt/domjudge/domserver/etc/domjudge-fpm.conf /etc/php/7.4/fpm/pool.d/domjudge.conf

sudo rm -f /etc/nginx/sites-enabled/*
sudo cp /opt/domjudge/domserver/etc/nginx-conf /etc/nginx/sites-enabled/domjudge

openssl req -nodes -new -x509 -keyout /tmp/server.key -out /tmp/server.crt -subj "/C=NL/ST=Noord-Holland/L=Amsterdam/O=TestingForPR/CN=localhost"
# shellcheck disable=SC2002
cat "$(pwd)/.github/workflowscripts/nginx_extra" | sudo tee -a /etc/nginx/sites-enabled/domjudge
sudo nginx -t
section_end

section_start "Show webserver is up"
for service in nginx php7.4-fpm; do
    sudo systemctl restart $service
    sudo systemctl status  $service
done
section_end
