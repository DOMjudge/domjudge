#!/bin/bash

. .github/jobs/ci_settings.sh

if [ -n "$1" ] && [ "$1" != "default" ]; then
export ARCH="$1"
fi

function finish() {
    echo -e "\\n\\n=======================================================\\n"
    echo "Storing artifacts..."
    trace_on
    set +e
    cp /proc/cmdline "$ARTIFACTS/cmdline"
    cp /__w/domjudge/domjudge/chroot/domjudge/etc/apt/sources.list "$ARTIFACTS/sources.list"
    cp /__w/domjudge/domjudge/chroot/domjudge/debootstrap/debootstrap.log "$ARTIFACTS/debootstrap.log"
}

FAILED=0

trap finish EXIT

DIR=$PWD
section_start "Debug info"
lsb_release -a   | tee -a "$ARTIFACTS/debug-info"
mount            | tee -a "$ARTIFACTS/debug-info"
whoami           | tee -a "$ARTIFACTS/debug-info"
echo "Dir: $DIR" | tee -a "$ARTIFACTS/debug-info"
section_end

section_start "Basic judgehost install"
chown -R domjudge ./

# configure, make and install (but skip documentation)
sudo -u domjudge make configure
sudo -u domjudge ./configure --with-baseurl='http://localhost/domjudge/' --with-domjudge-user=domjudge --with-judgehost_chrootdir=${DIR}/chroot/domjudge |& tee "$ARTIFACTS/configure.log"
sudo -u domjudge make judgehost |& tee "$ARTIFACTS/make.log"
make install-judgehost          |& tee -a "$ARTIFACTS/make.log"
section_end setup

section_start "Configure chroot"

cd /opt/domjudge/judgehost/bin || exit 1
section_end chroot

section_start "Show minimal chroot"
./dj_make_chroot -a "$ARCH" | tee -a "$ARTIFACTS"/chroot.log
section_end

section_start "Test chroot contents"
set -xe
cp ${DIR}/submit/assert.bash .
cp ${DIR}/.github/jobs/chroot.bats .
bats ./chroot.bats
section_end
