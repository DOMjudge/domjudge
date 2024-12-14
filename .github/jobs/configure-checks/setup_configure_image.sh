#!/bin/sh

set -eux

distro_id=$(grep "^ID=" /etc/os-release)

# Install everything for configure and testing
case $distro_id in
    "ID=fedora")
        dnf install pkg-config make bats autoconf automake util-linux -y ;;
    *)
        apt-get update; apt-get full-upgrade -y
        apt-get install pkg-config make bats autoconf -y ;;
esac

# Build the configure file
make configure

# Install extra assert statements for bots
cp submit/assert.bash .github/jobs/configure-checks/

# Run the configure tests for this usecase
test_path="/__w/domjudge/domjudge" bats .github/jobs/configure-checks/all.bats
