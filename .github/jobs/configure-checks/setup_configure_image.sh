#!/bin/bash

set -euxo pipefail

distro_id=$(grep "^ID=" /etc/os-release)

# Install everything for configure and testing
case $distro_id in
    "ID=arch")
        pacman -Sy --noconfirm make bats tar ;;
    "ID=fedora")
        dnf install make bats -y ;;
    'ID="opensuse-leap"')
        zypper install -y make bats ;;
    *)
        apt-get update; apt-get full-upgrade -y
        apt-get install make bats -y ;;
esac

# Install extra assert statements for bots
cp submit/assert.bash .github/jobs/configure-checks/

# Run the configure tests for this usecase
test_path="/__w/domjudge/domjudge/release" bats .github/jobs/configure-checks/all.bats
