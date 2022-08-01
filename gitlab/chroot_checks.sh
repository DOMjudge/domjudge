#!/bin/bash

. gitlab/ci_settings.sh

function finish() {
    echo -e "\\n\\n=======================================================\\n"
    echo "Storing artifacts..."
    trace_on
    set +e
    cp /proc/cmdline "$GITLABARTIFACTS/cmdline"
    cp /chroot/domjudge/etc/apt/sources.list "$GITLABARTIFACTS/sources.list"
    cp /chroot/domjudge/debootstrap/debootstrap.log "$GITLABARTIFACTS/debootstrap.log"
}
trap finish EXIT

section_start setup "Setup and install"
lsb_release -a

# configure, make and install (but skip documentation)
make configure
./configure --with-baseurl='http://localhost/domjudge/' --with-domjudge-user=domjudge --with-judgehost_chrootdir=${DIR}/chroot/domjudge |& tee "$GITLABARTIFACTS/configure.log"
make judgehost |& tee "$GITLABARTIFACTS/make.log"
sudo make install-judgehost |& tee -a "$GITLABARTIFACTS/make.log"
section_end setup

section_start mount "Show runner mounts"
# Currently gitlab has some runners with noexec/nodev,
# This can be removed if we have more stable runners.
mount -o remount,exec,dev /builds
section_end mount

section_start chroot "Configure chroot"

if [ -e ${DIR}/chroot/domjudge ]; then
    rm -rf ${DIR}/chroot/domjudge
fi

cd ${DIR}/misc-tools || exit 1
section_end chroot

section_start chroottest "Test chroot contents"
cp ${DIR}/submit/assert.bash ./
cp ${DIR}/gitlab/chroot.bats ./
bats ./chroot.bats
section_end chroottest
