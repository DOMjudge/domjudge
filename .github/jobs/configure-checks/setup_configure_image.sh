#!/bin/bash

. .github/jobs/ci_settings.sh

set -euxo pipefail

ghtest="$1"
export ghtest

section_start "Install all tools"
distro_id="$(grep "^ID=" /etc/os-release)"

# Install everything for configure and testing
case $distro_id in
    "ID=arch")
        pacman -Sy --noconfirm bats ;;
    "ID=alpine")
        apk add bash make bats tar ;;
    "ID=fedora")
        dnf install make bats util-linux -y ;;
    'ID="opensuse-leap"')
        zypper install -y make bats ;;
    *)
        apt-get update; apt-get full-upgrade -y
        apt-get install make bats -y ;;
esac

# Install extra assert statements for bots
cp submit/assert.bash .github/jobs/configure-checks/
section_end

# Run the configure tests for this usecase
test_path="/__w/domjudge/domjudge/release"
export test_path

if [ "${ghtest:?}" = "distclean" ]; then
    .github/jobs/configure-checks/distclean.bats
else
    .github/jobs/configure-checks/all.bats
fi
