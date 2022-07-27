#!/bin/bash

. gitlab/ci_settings.sh

function finish() {
    echo -e "\\n\\n=======================================================\\n"
    echo "Storing artifacts..."
    trace_on
    set +e
    mysqldump domjudge > "$GITLABARTIFACTS/db.sql"
    cp /var/log/nginx/domjudge.log "$GITLABARTIFACTS/nginx.log"
    cp /opt/domjudge/domserver/webapp/var/log/prod.log "$GITLABARTIFACTS/symfony.log"
    cp /opt/domjudge/domserver/webapp/var/log/prod.log.errors "$GITLABARTIFACTS/symfony_errors.log"
    cp /tmp/judgedaemon.log "$GITLABARTIFACTS/judgedaemon.log"
    cp /proc/cmdline "$GITLABARTIFACTS/cmdline"
    cp /chroot/domjudge/etc/apt/sources.list "$GITLABARTIFACTS/sources.list"
    cp /chroot/domjudge/debootstrap/debootstrap.log "$GITLABARTIFACTS/debootstrap.log"
    cp "${DIR}/misc-tools/icpctools/*json" "$GITLABARTIFACTS/"
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
mount
# Currently gitlab has some runners with noexec/nodev,
# This can be removed if we have more stable runners.
mount -o remount,exec,dev /builds
section_end mount

section_start chroot "Configure chroot"

if [ -e ${DIR}/chroot/domjudge ]; then
    rm -rf ${DIR}/chroot/domjudge
fi

cd ${DIR}/misc-tools || exit 1
#for arch in amd64,arm64,""
#for dir in "/chroot","/builds/chroot","/notadir/chroot"
#for dist in "Debian","Ubuntu","notLinux"
#for rel in "buster","wheeze","focal","bionic","notarelease"
#for incdeb in "zip","nano"
#for remdeb in "gcc","pypy3"
#for locdeb in "vim.deb","helloworld.deb"
#for mirror in "http://mirror.yandex.ru/debian","http://mirror.yandex.ru/debian"
#for overwrite in "1","0"
#for force in "1","0"
#for help in "1","0"

#ARGS=""
#if [ -n "${ARCH+x}" ]; then
#    ARGS="$ARGS -a ${ARCH}"
#fi
#if [ -n "${DISTRO+x}" ]; then
#    ARGS="$ARGS -D ${DISTRO}"
#fi
#if [ -n "${RELEASE+x}" ]; then
#    ARGS="$ARGS -R ${RELEASE}"
#fi
#sudo ./dj_make_chroot ${ARGS} |& tee "$GITLABARTIFACTS/dj_make_chroot.log"
section_end chroot

section_start chroottest "Test chroot contents"
cp submit/assert.bash ./
bats ./chroot_tests.bats
sudo ./dj_run_chroot "pypy3"
