#!/bin/bash

set -euxo pipefail

distro_id=$(grep "^ID=" /etc/os-release)

# Install everything for configure and testing
case $distro_id in
    "ID=fedora")
        dnf install pkg-config make bats autoconf automake util-linux -y ;;
    *)
        apt-get update; apt-get full-upgrade -y
        # Install g++-12 to make sure we can use -std=c++20
        version=$(g++ -dumpversion 2>/dev/null | awk -F'.' '{print $1}' 2>/dev/null || echo 0); [ "${version}" -lt 12 ] && apt install -y g++-12
        apt-get install pkg-config make bats autoconf -y ;;
esac

# Build the configure file
make configure

# Install extra assert statements for bots
cp submit/assert.bash .github/jobs/configure-checks/

# Run the configure tests for this usecase
mkdir /tmp/bats_logs
test_path="/__w/domjudge/domjudge" bats --print-output-on-failure --gather-test-outputs-in /tmp/bats_logs .github/jobs/configure-checks/all.bats
