#!/bin/bash

set -euxo pipefail

distro_id=$(grep "^ID=" /etc/os-release)

# Install everything for configure and testing
case $distro_id in
    "ID=fedora")
        # Keep downloaded packages, see below.
        echo "keepcache=True" >> /etc/dnf/dnf.conf
        dnf install pkg-config make bats autoconf automake util-linux zip -y ;;
    *)
        # Keep downloaded packages (the image deletes them by default), so
        # the checks can reinstall anything they remove from the cache.
        rm -f /etc/apt/apt.conf.d/docker-clean
        apt-get update; apt-get full-upgrade -y
        # Install g++-12 to make sure we can use -std=c++20
        version=$(g++ -dumpversion 2>/dev/null | awk -F'.' '{print $1}' 2>/dev/null || echo 0); [ "${version}" -lt 12 ] && apt-get install -y g++-12
        apt-get install pkg-config make bats autoconf php zip -y ;;
esac

# Download the packages the checks install, so that every check installs
# them from the cache in this image instead of from the network.
case $distro_id in
    "ID=fedora")
        dnf install --downloadonly -y gcc g++ libcgroup-devel composer httpd nginx ;;
    *)
        apt-get install --download-only -y gcc g++ clang libcgroup-dev
        # Not every release has composer, the checks then use curl instead.
        apt-get install --download-only -y composer || apt-get install --download-only -y curl ;;
esac

# Build the configure file
make configure

# Install extra assert statements for bots
cp submit/assert.bash .github/jobs/configure-checks/

# Run the configure tests for this usecase
mkdir /tmp/bats_logs
