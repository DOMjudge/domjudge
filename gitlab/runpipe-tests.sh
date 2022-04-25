#!/bin/bash

. gitlab/ci_settings.sh

section_start compile "Compile runpipe"
# Configure and make the runpipe binaries.
make configure
./configure --with-baseurl='http://localhost/domjudge/' --with-domjudge-user=domjudge --with-judgehost_chrootdir=${DIR}/chroot/domjudge |& tee "$GITLABARTIFACTS/configure.log"
make judgehost |& tee "$GITLABARTIFACTS/make.log"
section_end compile

cd judge/runpipe_test || exit 1
make test |& tee "$GITLABARTIFACTS/test.log"
