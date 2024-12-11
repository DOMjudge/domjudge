#!/usr/bin/env bats

load 'assert'

u="domjudge-bats-user"

distro_id=$(grep "^ID=" /etc/os-release)

cmd="apt-get"
if [ "$distro_id" = "ID=fedora" ]; then
    cmd=dnf
fi

translate () {
    args="$@"
    if [ "$distro_id" = "ID=fedora" ]; then
        args=${args/libcgroup-dev/libcgroup-devel}
    fi
    echo "$args"
}

if [ -z ${test_path+x} ]; then
    test_path="/domjudge"
    # Used in the CI
fi

setup_user() {
    id -u $u || (useradd $u ; groupadd $u || true )>/dev/null
    chown -R $u:$u ./
}

setup() {
    setup_user
    for shared_file in config.log confdefs.h conftest.err; do
        chmod a+rw $shared_file || true
    done
    if [ "$distro_id" = "ID=fedora" ]; then
        repo-install httpd
    fi
    repo-install gcc g++ libcgroup-dev composer
}

run_configure () {
    su $u -c "./configure $*"
}

repo-install () {
    args=$(translate $@)
    ${cmd} install $args -y >/dev/null
}
repo-remove () {
    args=$(translate $@)
    ${cmd} remove $args -y #>/dev/null
    if [ "$distro_id" != "ID=fedora" ]; then
        apt-get autoremove -y 2>/dev/null
    fi
}

@test "Default empty configure" {
    # cleanup from earlier runs
    repo-remove gcc g++ clang
    run ./configure
    assert_failure 1
    assert_line "checking whether configure should try to set CFLAGS... yes"
    assert_line "checking whether configure should try to set CXXFLAGS... yes"
    assert_line "checking whether configure should try to set LDFLAGS... yes"
    assert_line "checking for gcc... no"
    assert_line "checking for cc... no"
    assert_line "checking for cl.exe... no"
    assert_regex "configure: error: in .${test_path}':"
    assert_line 'configure: error: no acceptable C compiler found in $PATH'
    assert_regex "See [\`']config.log' for more details"
}

compiler_assertions () {
    run run_configure
    # Depending on where we run this we might runas wrong user or lack libraries
    # so we can't expect either success or failure.
    assert_line "checking baseurl... https://example.com/domjudge/"
    assert_line "checking whether configure should try to set CFLAGS... yes"
    assert_line "checking whether configure should try to set CXXFLAGS... yes"
    assert_line "checking whether configure should try to set LDFLAGS... yes"
    assert_line "checking whether the C compiler works... yes"
    assert_line "checking for C compiler default output file name... a.out"
    assert_line "checking for suffix of executables... "
    assert_line "checking whether we are cross compiling... no"
    assert_line "checking for suffix of object files... o"
    assert_regex "checking whether .*GNU C.*\.\.\. yes"
    assert_line "checking whether C compiler accepts -Wall... yes"
    assert_line "checking whether C compiler accepts -fstack-protector... yes"
    assert_line "checking whether C compiler accepts -fPIE... yes"
    assert_line "checking whether C compiler accepts -D_FORTIFY_SOURCE=2... yes"
    assert_line "checking whether the linker accepts -fPIE... yes"
    assert_line "checking whether the linker accepts -pie... yes"
    assert_line "checking whether the linker accepts -Wl,-z,relro... yes"
    assert_line "checking whether the linker accepts -Wl,-z,now... yes"
    assert_line "checking whether $1 accepts -g... yes"
    assert_regex "^checking for $1 option to (enable C11 features|accept ISO C89)\.\.\. none needed$"
    assert_line "checking whether $1 accepts -g... (cached) yes"
    if [ -n "$2" ]; then
        assert_line "checking whether $2 accepts -g... yes"
        assert_line "checking how to run the C preprocessor... $1 -E"
        assert_line "checking how to run the C++ preprocessor... $2 -E"
    fi
}

compile_assertions_finished () {
    assert_line " * CFLAGS..............: -g -O2 -Wall -Wformat -Wformat-security -pedantic -fstack-protector -fPIE -D_FORTIFY_SOURCE=2 -std=c11"
    assert_line " * CXXFLAGS............: -g -O2 -Wall -Wformat -Wformat-security -pedantic -fstack-protector -fPIE -D_FORTIFY_SOURCE=2 -std=c++11"
    assert_line " * LDFLAGS.............:  -fPIE -pie -Wl,-z,relro -Wl,-z,now"
}

@test "Install GNU C only" {
    if [ "$distro_id" = "ID=fedora" ]; then
        # Fedora ships with a gcc with enough C++ support
        skip
    fi
    repo-remove clang g++
    repo-install gcc libcgroup-dev
    compiler_assertions gcc ''
    assert_line "checking for gcc... gcc"
    assert_line "checking whether gcc accepts -g... yes"
    assert_line "configure: error: C++ preprocessor \"/lib/cpp\" fails sanity check"
}

@test "Install GNU C++ only" {
    # This does work due to dependencies
    repo-remove clang gcc
    repo-install g++ libcgroup-dev
    compiler_assertions gcc g++
    assert_line "checking for gcc... gcc"
    assert_line "checking for g++... g++"
    compile_assertions_finished
}

@test "Install C/C++ compilers (Clang as alternative)" {
    if [ "$distro_id" = "ID=fedora" ]; then
        # Fedora has gcc as dependency for clang
        skip
    fi
    repo-remove gcc g++
    repo-install clang libcgroup-dev
    compiler_assertions cc c++
    assert_line "checking for gcc... no"
    assert_line "checking for cc... cc"
    assert_line "checking for g++... no"
    assert_line "checking for c++... c++"
    compile_assertions_finished
}

@test "Run as root discouraged" {
   setup
   run su root -c "./configure"
   discourage_root="checking domjudge-user... configure: error: installing/running as root is STRONGLY DISCOURAGED, use --with-domjudge-user=root to override."
   assert_line "$discourage_root"
   run su root -c "./configure --with-domjudge-user=root"
   refute_line "$discourage_root"
}

@test "Check for missing webserver group" {
    if [ "$distro_id" != "ID=fedora" ]; then
        # Debian/Ubuntu start with a www-data group
        skip
    fi
    repo-remove httpd nginx
    for www_group in nginx apache; do
        userdel ${www_group} || true
        groupdel ${www_group} || true
    done
    run ./configure --with-domjudge-user=$u
    assert_line "checking webserver-group... configure: error: webserver group could not be detected, use --with-webserver-group=GROUP"
}

@test "Check for newly added webserver group (Apache)" {
    if [ "$distro_id" != "ID=fedora" ]; then
        # Debian/Ubuntu start with a www-data group
        skip
    fi
    repo-remove httpd nginx
    for www_group in nginx apache; do
        userdel ${www_group} || true
        groupdel ${www_group} || true
    done
    repo-install httpd
    run ./configure --with-domjudge-user=$u
    assert_line "checking webserver-group... apache (detected)"
    assert_line " * webserver group.....: apache"
}

@test "Check for newly added webserver group (Nginx)" {
    if [ "$distro_id" != "ID=fedora" ]; then
        # Debian/Ubuntu start with a www-data group
        skip
    fi
    repo-remove httpd nginx
    for www_group in nginx apache; do
        userdel ${www_group} || true
        groupdel ${www_group} || true
    done
    repo-install nginx
    run ./configure --with-domjudge-user=$u
    assert_line "checking webserver-group... nginx (detected)"
    assert_line " * webserver group.....: nginx"
}

@test "Run as normal user" {
   setup
   run ./configure --with-domjudge-user=$u
   assert_line "checking domjudge-user... $u"
   run su $u -c "./configure"
   assert_line "checking domjudge-user... $u (default: current user)"
}

@test "cgroup library needed" {
   cgroup_init_find="checking for cgroup_init in -lcgroup... no"
   cgroup_init_error="configure: error: Linux cgroup library not found."
   setup_user
   repo-install gcc g++
   repo-remove libcgroup-dev
   run run_configure
   assert_line "$cgroup_init_find"
   assert_line "$cgroup_init_error"
   repo-install libcgroup-dev
   run run_configure
   refute_line "$cgroup_init_find"
   refute_line "$cgroup_init_error"
}

@test "/opt configured" {
   setup
   run run_configure
   assert_line " * prefix..............: /opt/domjudge"
   assert_line " * documentation.......: /opt/domjudge/doc"
   assert_line " * domserver...........: /opt/domjudge/domserver"
   assert_line "    - bin..............: /opt/domjudge/domserver/bin"
   assert_line "    - etc..............: /opt/domjudge/domserver/etc"
   assert_line "    - lib..............: /opt/domjudge/domserver/lib"
   assert_line "    - log..............: /opt/domjudge/domserver/log"
   assert_line "    - run..............: /opt/domjudge/domserver/run"
   assert_line "    - sql..............: /opt/domjudge/domserver/sql"
   assert_line "    - tmp..............: /opt/domjudge/domserver/tmp"
   assert_line "    - webapp...........: /opt/domjudge/domserver/webapp"
   assert_line "    - example_problems.: /opt/domjudge/domserver/example_problems"
   assert_line " * judgehost...........: /opt/domjudge/judgehost"
   assert_line "    - bin..............: /opt/domjudge/judgehost/bin"
   assert_line "    - etc..............: /opt/domjudge/judgehost/etc"
   assert_line "    - lib..............: /opt/domjudge/judgehost/lib"
   assert_line "    - libjudge.........: /opt/domjudge/judgehost/lib/judge"
   assert_line "    - log..............: /opt/domjudge/judgehost/log"
   assert_line "    - run..............: /opt/domjudge/judgehost/run"
   assert_line "    - tmp..............: /opt/domjudge/judgehost/tmp"
   assert_line "    - judge............: /opt/domjudge/judgehost/judgings"
   assert_line "    - chroot...........: /chroot/domjudge"
}

@test "Prefix configured" {
   setup
   run run_configure --prefix=/tmp
   refute_line " * prefix..............: /opt/domjudge"
   refute_line " * documentation.......: /opt/domjudge/doc"
   refute_line " * domserver...........: /opt/domjudge/domserver"
   refute_line "    - bin..............: /opt/domjudge/domserver/bin"
   refute_line "    - tmp..............: /opt/domjudge/domserver/tmp"
   refute_line "    - example_problems.: /opt/domjudge/domserver/example_problems"
   refute_line " * judgehost...........: /opt/domjudge/judgehost"
   refute_line "    - libjudge.........: /opt/domjudge/judgehost/lib/judge"
   refute_line "    - log..............: /opt/domjudge/judgehost/log"
   refute_line "    - run..............: /opt/domjudge/judgehost/run"
   refute_line "    - tmp..............: /opt/domjudge/judgehost/tmp"
   refute_line "    - judge............: /opt/domjudge/judgehost/judgings"
   assert_line " * prefix..............: /tmp"
   assert_line " * documentation.......: /tmp/doc"
   assert_line " * domserver...........: /tmp/domserver"
   assert_line " * judgehost...........: /tmp/judgehost"
   assert_line "    - judge............: /tmp/judgehost/judgings"
}

@test "Check FHS" {
   setup
   run run_configure --enable-fhs
   refute_line " * prefix..............: /opt/domjudge"
   refute_line " * documentation.......: /opt/domjudge/doc"
   refute_line " * domserver...........: /opt/domjudge/domserver"
   refute_line "    - webapp...........: /opt/domjudge/domserver/webapp"
   refute_line "    - example_problems.: /opt/domjudge/domserver/example_problems"
   refute_line " * judgehost...........: /opt/domjudge/judgehost"
   refute_line "    - lib..............: /opt/domjudge/judgehost/lib"

   assert_line " * prefix..............: /usr/local"
   assert_line " * documentation.......: /usr/local/share/doc/domjudge"
   assert_line " * domserver...........: "
   assert_line "    - bin..............: /usr/local/bin"
   assert_line "    - etc..............: /usr/local/etc/domjudge"
   assert_line "    - lib..............: /usr/local/lib/domjudge"
   assert_line "    - log..............: /usr/local/var/log/domjudge"
   assert_line "    - run..............: /usr/local/var/run/domjudge"
   assert_line "    - sql..............: /usr/local/share/domjudge/sql"
   assert_line "    - tmp..............: /tmp"
   assert_line "    - webapp...........: /usr/local/share/domjudge/webapp"
   assert_line "    - example_problems.: /usr/local/share/domjudge/example_problems"
   assert_line " * judgehost...........: "
   assert_line "    - bin..............: /usr/local/bin"
   assert_line "    - etc..............: /usr/local/etc/domjudge"
   assert_line "    - lib..............: /usr/local/lib/domjudge"
   assert_line "    - libjudge.........: /usr/local/lib/domjudge/judge"
   assert_line "    - log..............: /usr/local/var/log/domjudge"
   assert_line "    - run..............: /usr/local/var/run/domjudge"
   assert_line "    - tmp..............: /tmp"
   assert_line "    - judge............: /usr/local/var/lib/domjudge/judgings"
   assert_line "    - chroot...........: /chroot/domjudge"
}

@test "Alternative dirs together with FHS" {
   setup
   run run_configure --enable-fhs --with-domserver_webappdir=/run/webapp --with-domserver_tmpdir=/tmp/domserver --with-judgehost_tmpdir=/srv/tmp --with-judgehost_judgedir=/srv/judgings --with-judgehost_chrootdir=/srv/chroot/domjudge
   assert_line " * prefix..............: /usr/local"
   assert_line " * documentation.......: /usr/local/share/doc/domjudge"
   assert_line " * domserver...........: "
   assert_line "    - bin..............: /usr/local/bin"
   assert_line "    - etc..............: /usr/local/etc/domjudge"
   assert_line "    - lib..............: /usr/local/lib/domjudge"
   assert_line "    - log..............: /usr/local/var/log/domjudge"
   assert_line "    - run..............: /usr/local/var/run/domjudge"
   assert_line "    - sql..............: /usr/local/share/domjudge/sql"
   refute_line "    - tmp..............: /tmp"
   assert_line "    - tmp..............: /tmp/domserver"
   refute_line "    - webapp...........: /usr/local/share/domjudge/webapp"
   assert_line "    - webapp...........: /run/webapp"
   assert_line "    - example_problems.: /usr/local/share/domjudge/example_problems"
   assert_line " * judgehost...........: "
   assert_line "    - bin..............: /usr/local/bin"
   assert_line "    - etc..............: /usr/local/etc/domjudge"
   assert_line "    - lib..............: /usr/local/lib/domjudge"
   assert_line "    - libjudge.........: /usr/local/lib/domjudge/judge"
   assert_line "    - log..............: /usr/local/var/log/domjudge"
   assert_line "    - run..............: /usr/local/var/run/domjudge"
   refute_line "    - tmp..............: /tmp"
   assert_line "    - tmp..............: /srv/tmp"
   refute_line "    - judge............: /usr/local/var/lib/domjudge/judgings"
   assert_line "    - judge............: /srv/judgings"
   refute_line "    - chroot...........: /chroot/domjudge"
   assert_line "    - chroot...........: /srv/chroot/domjudge"
}

@test "Alternative dirs together with defaults" {
   setup
   run run_configure "--with-judgehost_tmpdir=/srv/tmp --with-judgehost_judgedir=/srv/judgings --with-judgehost_chrootdir=/srv/chroot --with-domserver_logdir=/log"
   assert_line " * prefix..............: /opt/domjudge"
   assert_line " * documentation.......: /opt/domjudge/doc"
   assert_line " * domserver...........: /opt/domjudge/domserver"
   refute_line "    - log..............: /opt/domjudge/domserver/log"
   assert_line "    - log..............: /log"
   assert_line " * judgehost...........: /opt/domjudge/judgehost"
   refute_line "    - tmp..............: /opt/domjudge/judgehost/tmp"
   assert_line "    - tmp..............: /srv/tmp"
   refute_line "    - judge............: /opt/domjudge/judgehost/judgings"
   assert_line "    - judge............: /srv/judgings"
   refute_line "    - chroot...........: /chroot/domjudge"
   assert_line "    - chroot...........: /srv/chroot"
}

@test "Default URL not set, docs mention" {
  setup
  run run_configure
  assert_line "checking baseurl... https://example.com/domjudge/"
  assert_line "Warning: base URL is unconfigured; generating team documentation will"
  assert_line "not work out of the box!"
  assert_line "Rerun configure with option '--with-baseurl=BASEURL' to correct this."
  assert_line " * website base URL....: https://example.com/domjudge/"
  assert_line " * documentation.......: /opt/domjudge/doc"
  run run_configure "--with-baseurl=https://contest.example.org"
  assert_line "checking baseurl... https://contest.example.org"
  refute_line "Warning: base URL is unconfigured; generating team documentation will"
  refute_line "not work out of the box!"
  refute_line "Rerun configure with option '--with-baseurl=BASEURL' to correct this."
  assert_line " * website base URL....: https://contest.example.org"
}

@test "Change users" {
  setup
  run run_configure
  assert_line " * default user........: domjudge-bats-user"
  assert_line " * runguard user.......: domjudge-run"
  assert_line " * runguard group......: domjudge-run"
  assert_regex "^ \* webserver group\.\.\.\.\.: (www-data|apache|nginx)$"
  run run_configure "--with-domjudge-user=other_user --with-webserver-group=other_group --with-runuser=other-domjudge-run --with-rungroup=other-run-group"
  refute_line " * default user........: domjudge-bats-user"
  refute_line " * runguard user.......: domjudge-run"
  refute_line " * runguard group......: domjudge-run"
  for group in www-data apache nginx; do
    refute_line " * webserver group.....: $group"
  done
  assert_line " * default user........: other_user"
  assert_line " * runguard user.......: other-domjudge-run"
  assert_line " * runguard group......: other-run-group"
  assert_line " * webserver group.....: other_group"
}

@test "No docs" {
  setup
  run run_configure
  assert_line " * documentation.......: /opt/domjudge/doc"
  run run_configure --enable-doc-build
  assert_line " * documentation.......: /opt/domjudge/doc"
  run run_configure --disable-doc-build
  assert_line " * documentation.......: /opt/domjudge/doc (disabled)"
}

@test "Build default (effective host does both domserver & judgehost)" {
  if [ "$distro_id" = "ID=fedora" ]; then
      # Fails as libraries are not found
      skip
  fi
  setup
  run run_configure
  assert_line " * domserver...........: /opt/domjudge/domserver"
  assert_regex "^ \* webserver group\.\.\.\.\.: (www-data|apache|nginx)$"
  assert_line " * judgehost...........: /opt/domjudge/judgehost"
  assert_line " * runguard group......: domjudge-run"
  run make domserver
  assert_success
  run make judgehost
  assert_success
}

@test "Build domserver disabled" {
  if [ "$distro_id" = "ID=fedora" ]; then
      # Fails as libraries are not found
      skip
  fi
  setup
  run run_configure --disable-domserver-build
  refute_line " * domserver...........: /opt/domjudge/domserver"
  for group in www-data apache nginx; do
    refute_line " * webserver group.....: $group"
  done
  assert_line " * judgehost...........: /opt/domjudge/judgehost"
  assert_line " * runguard group......: domjudge-run"
  run make domserver
  assert_failure
  run make judgehost
  assert_success
}

@test "Build judgehost disabled" {
  if [ "$distro_id" = "ID=fedora" ]; then
      # Fails as libraries are not found
      skip
  fi
  setup
  run run_configure --disable-judgehost-build
  assert_line " * domserver...........: /opt/domjudge/domserver"
  assert_regex "^ \* webserver group\.\.\.\.\.: (www-data|apache|nginx)$"
  refute_line " * judgehost...........: /opt/domjudge/judgehost"
  refute_line " * runguard group......: domjudge-run"
  run make domserver
  assert_success
  run make judgehost
  assert_failure
}
