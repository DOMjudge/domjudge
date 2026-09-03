#!/usr/bin/env bats

load 'assert'

source .github/jobs/configure-checks/functions.sh

@test "Check for missing webserver group" {
    if [ "$distro_id" != "ID=fedora" ]; then
        # Debian/Ubuntu start with a www-data group
        skip
    fi
    repo-install gcc g++
    repo-remove httpd nginx
    for www_group in nginx apache; do
        userdel ${www_group} || true
        groupdel ${www_group} || true
    done
    run ./configure --with-domjudge-user=$u
    assert_line "checking webserver-group... configure: error: webserver group could not be detected, use --with-webserver-group=GROUP"
}

@test "Run as root discouraged" {
   setup
   run su root -c "./configure"
   discourage_root="checking domjudge-user... configure: error: installing/running as root is STRONGLY DISCOURAGED, use --with-domjudge-user=root to override."
   assert_line "$discourage_root"
   run su root -c "./configure --with-domjudge-user=root"
   refute_line "$discourage_root"
}

@test "Run as normal user" {
   setup
   run ./configure --with-domjudge-user=$u
   assert_line "checking domjudge-user... $u"
   run su $u -c "./configure"
   assert_line "checking domjudge-user... $u (default: current user)"
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

@test "URL path prefix is derived from the base URL" {
  setup
  # Without a base URL the historic default is kept.
  run run_configure
  assert_line "checking URL path prefix... /domjudge/"
  run run_configure "--with-baseurl=https://contest.example.org/dj/"
  assert_line "checking URL path prefix... /dj/"
  # A base URL without a path serves DOMjudge from the root.
  run run_configure "--with-baseurl=https://contest.example.org/"
  assert_line "checking URL path prefix... /"
  # An unparseable base URL is rejected.
  run run_configure "--with-baseurl=not-a-url"
  assert_line "checking URL path prefix... configure: error: could not parse base URL 'not-a-url'; expected e.g. 'https://example.com/domjudge/'."
}

@test "Webserver config follows the URL path prefix" {
  setup
  run run_configure "--with-baseurl=http://localhost/domjudge/"
  run make -C etc config
  assert_success
  run grep -q "^Alias /domjudge " etc/apache.conf
  assert_success
  run grep -q "^set \$prefix /domjudge;" etc/nginx-conf-inner
  assert_success
  run grep -q "^location /domjudge { return 301 /domjudge/; }" etc/nginx-conf-inner
  assert_success
  run grep -q "^DocumentRoot " etc/apache.conf
  assert_failure
  # The prose describing the other case is dropped along with its directive.
  run grep -q "takes over" etc/apache.conf
  assert_failure

  # Served from the root: Apache takes over the document root, and nginx
  # needs an explicit empty string since 'set' requires two arguments.
  run run_configure "--with-baseurl=http://localhost/"
  run make -C etc config
  assert_success
  run grep -q "^DocumentRoot " etc/apache.conf
  assert_success
  run grep -q "^Alias " etc/apache.conf
  assert_failure
  run grep -q "only lets it occupy a subdir" etc/apache.conf
  assert_failure
  run grep -q "^set \$prefix '';" etc/nginx-conf-inner
  assert_success
  run grep -q "return 301" etc/nginx-conf-inner
  assert_failure

  # No sentinel markers or unsubstituted tokens may survive either way.
  for file in apache.conf nginx-conf nginx-conf-inner domjudge-fpm.conf; do
    run grep -q "ONLY_IF" "etc/$file"
    assert_failure
    run grep -qE "@[A-Za-z_]+@" "etc/$file"
    assert_failure
  done
}

@test "Instance name is sanitized" {
  setup
  run run_configure
  assert_line "checking instance name... domjudge"
  assert_line " * instance name.......: domjudge"
  # Uppercase and characters outside [a-z0-9-] are folded to dashes.
  run run_configure "--with-instance-name=wt-Foo.Bar"
  assert_line "checking instance name... wt-foo-bar"
  # Truncated to 24 characters, without leaving a trailing dash.
  run run_configure "--with-instance-name=a-very-long-worktree-name-that-exceeds-limits"
  assert_line "checking instance name... a-very-long-worktree-nam"
  # A name with nothing usable in it is an error rather than a silent
  # fallback to the default instance.
  run run_configure "--with-instance-name=___"
  assert_line "checking instance name... configure: error: instance name '___' contains no usable characters; pass a valid --with-instance-name=NAME."
  # Autoconf turns a valueless option into 'yes'/'no'; neither names an
  # instance, so they must not become one.
  run run_configure "--with-instance-name"
  assert_line "checking instance name... configure: error: --with-instance-name requires a value, e.g. --with-instance-name=contest2."
  run run_configure "--without-instance-name"
  assert_line "checking instance name... configure: error: --with-instance-name requires a value, e.g. --with-instance-name=contest2."
  run run_configure "--with-instance-name="
  assert_line "checking instance name... configure: error: --with-instance-name requires a non-empty value, e.g. --with-instance-name=contest2."
  # A later occurrence wins, so the makefiles can pass a derived default
  # that the user overrides through CONFIGURE_FLAGS.
  run run_configure "--with-instance-name=first --with-instance-name=second"
  assert_line "checking instance name... second"
}

@test "Named instance gets its own webserver identifiers" {
  setup
  run run_configure "--with-instance-name=wt-foo --with-baseurl=http://wt-foo.localhost/"
  assert_line "checking webserver host... wt-foo.localhost:80"
  run make -C etc config
  assert_success
  # These nginx names are global; a duplicate makes nginx refuse to start.
  run grep -q "^upstream wt-foo {" etc/nginx-conf
  assert_success
  run grep -q "fastcgi_param_https_wt_foo" etc/nginx-conf
  assert_success
  run grep -q "^server_name wt-foo.localhost;" etc/nginx-conf-inner
  assert_success
  # PHP-FPM pool name and socket.
  run grep -q "^\[wt-foo\]" etc/domjudge-fpm.conf
  assert_success
  run grep -q "^listen = /var/run/php-fpm-wt-foo.sock" etc/domjudge-fpm.conf
  assert_success

  # An explicit port in the base URL is used, but an 'https' scheme must
  # not silently become 'listen 443': the generated server block speaks
  # plain HTTP, so it would answer TLS handshakes with cleartext.
  run run_configure "--with-instance-name=wt-foo --with-baseurl=http://wt-foo.localhost:8080/"
  assert_line "checking webserver host... wt-foo.localhost:8080"
  run run_configure "--with-instance-name=wt-foo --with-baseurl=https://wt-foo.example.org/"
  assert_line "checking webserver host... wt-foo.example.org:80"
  run make -C etc config
  assert_success
  run grep -q "^	listen 80;" etc/nginx-conf
  assert_success
  # The commented-out TLS block still mentions 443; no active one may.
  run grep -q "^	listen 443" etc/nginx-conf
  assert_failure
}

@test "Default instance keeps the historic webserver identifiers" {
  setup
  run run_configure "--with-baseurl=http://localhost/domjudge/"
  assert_line "checking webserver host... _default_:80"
  run make -C etc config
  assert_success
  run grep -q "^upstream domjudge {" etc/nginx-conf
  assert_success
  run grep -q "fastcgi_param_https_variable" etc/nginx-conf
  assert_success
  run grep -q "^server_name _default_;" etc/nginx-conf-inner
  assert_success
  run grep -q "^\[domjudge\]" etc/domjudge-fpm.conf
  assert_success
  run grep -q "^listen = /var/run/php-fpm-domjudge.sock" etc/domjudge-fpm.conf
  assert_success
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
  assert_failure 2
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
  assert_failure 2
}
