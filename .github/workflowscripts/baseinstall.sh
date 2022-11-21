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

export version="$1"

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

export PHPVERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION."\n";')

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

section_start "Set simple admin password"
echo "password" > ./etc/initial_admin_password.secret
echo "default login admin password password" > ~/.netrc
section_end

section_start "Install domserver"
make configure
./configure --with-baseurl='https://localhost/domjudge/' --enable-doc-build=no --prefix="/opt/domjudge"

make domserver
sudo make install-domserver
section_end

section_start "Explicit start mysql + install DB"
sudo /etc/init.d/mysql start

/opt/domjudge/domserver/bin/dj_setup_database -uroot -proot bare-install
section_end

section_start "Setup webserver"
sudo cp /opt/domjudge/domserver/etc/domjudge-fpm.conf /etc/php/$PHPVERSION/fpm/pool.d/domjudge.conf

sudo rm -f /etc/nginx/sites-enabled/*
sudo cp /opt/domjudge/domserver/etc/nginx-conf /etc/nginx/sites-enabled/domjudge

openssl req -nodes -new -x509 -keyout /tmp/server.key -out /tmp/server.crt -subj "/C=NL/ST=Noord-Holland/L=Amsterdam/O=TestingForPR/CN=localhost"
sudo cp /tmp/server.crt /usr/local/share/ca-certificates/
sudo update-ca-certificates
# shellcheck disable=SC2002
cat "$(pwd)/.github/workflowscripts/nginx_extra" | sudo tee -a /etc/nginx/sites-enabled/domjudge
sudo nginx -t
section_end

section_start "Show webserver is up"
for service in nginx php${PHPVERSION}-fpm; do
    sudo systemctl restart $service
    sudo systemctl status  $service
done
section_end

section_start "Install the example data"
/opt/domjudge/domserver/bin/dj_setup_database -uroot -proot install-examples
section_end

section_start "Setup user"
# We're using the admin user in all possible roles
echo "DELETE FROM userrole WHERE userid=1;" | mysql -uroot -proot domjudge
if [ "$version" = "team" ]; then
    # Add team to admin user
    echo "INSERT INTO userrole (userid, roleid) VALUES (1, 3);" | mysql -uroot -proot domjudge
    echo "UPDATE user SET teamid = 1 WHERE userid = 1;" | mysql -uroot -proot domjudge
elif [ "$version" = "jury" ]; then
    # Add jury to admin user
    echo "INSERT INTO userrole (userid, roleid) VALUES (1, 2);" | mysql -uroot -proot domjudge
elif [ "$version" = "balloon" ]; then
    # Add balloon to admin user
    echo "INSERT INTO userrole (userid, roleid) VALUES (1, 4);" | mysql -uroot -proot domjudge
elif [ "$version" = "admin" ]; then
    # Add admin to admin user
    echo "INSERT INTO userrole (userid, roleid) VALUES (1, 1);" | mysql -uroot -proot domjudge
fi
section_end

