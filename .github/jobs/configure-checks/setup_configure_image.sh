#!/bin/sh

set -eux

distro_id=$(grep "^ID=" /etc/os-release)

# Install everything for configure and testing
case $distro_id in
    "ID=fedora")
        grep -E 'nginx|apache' /etc/passwd || true
        grep -E 'nginx|apache' /etc/group || true

        dnf install -y pkg-config make bats autoconf automake util-linux \
            httpd php-fpm

        grep -E 'nginx|apache' /etc/passwd || true
        grep -E 'nginx|apache' /etc/group || true
        ;;
    *)
        apt-get update; apt-get full-upgrade -y
        apt-get install -y pkg-config make bats autoconf composer \
            php-fpm php-gd php-cli php-intl php-mbstring php-mysql \
            php-curl php-json php-xml php-zip
        ;;
esac

# Start from a configured, distribution-ready source tree. Ideally,
# we'd like to call `make dist` but that depends on LaTeX for building
# the documentation, so take a shortcut.
make configure

# Install extra assert statements for bats
cp submit/assert.bash .github/jobs/configure-checks/

# Run the configure tests for this usecase
test_path="/__w/domjudge/domjudge" bats --trace .github/jobs/configure-checks/all.bats
