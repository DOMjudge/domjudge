#!/bin/bash

. gitlab/ci_settings.sh

set -euxo pipefail

gitlabartifacts="$(pwd)/gitlabartifacts"
mkdir -p "$gitlabartifacts"

DIR=$(pwd)
lsb_release -a

GITSHA=$(git rev-parse HEAD || true)

section_start compile "Compile runpipe"
# Configure and make the runpipe binaries.
make configure
./configure --with-baseurl='http://localhost/domjudge/' --with-domjudge-user=domjudge --with-judgehost_chrootdir=${DIR}/chroot/domjudge |& tee "$gitlabartifacts/configure.log"
make judgehost |& tee "$gitlabartifacts/make.log"
section_end compile

cd judge/test
make test |& tee "$gitlabartifacts/test.log"
