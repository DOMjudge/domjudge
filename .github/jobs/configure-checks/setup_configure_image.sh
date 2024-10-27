#!/bin/sh

set -eux

distro_id=$(grep "^ID=" /etc/os-release)

# Install everything for configure and testing
case $distro_id in
    "ID=fedora")
        dnf install pkg-config make bats autoconf automake util-linux -y ;;
    *)
        apt-get update; apt-get full-upgrade -y
        apt-get install pkg-config make bats autoconf -y
        apt-get install composer php php-cli php-curl php-fpm php-gd \
                        php-intl php-json php-mbstring php-mysql php-xml php-zip \
                        acl zip unzip mariadb-server python3-sphinx \
                        python3-sphinx-rtd-theme rst2pdf fontconfig python3-yaml \
                        latexmk texlive-latex-recommended texlive-latex-extra \
                        tex-gyre -y ;;
esac

# Build the configure file
make configure

case $distro_id in
    "ID=fedora")
        true ;;
    *)
        (cd webapp; composer install --no-scripts ) ;;
esac

# Install extra assert statements for bots
cp submit/assert.bash .github/jobs/configure-checks/

# Run the configure tests for this usecase
test_path="/__w/domjudge/domjudge" bats .github/jobs/configure-checks/all.bats
