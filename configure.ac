AC_INIT([domjudge], [UNKNOWN], [domjudge@A-Eskwadraat.nl])
AC_CONFIG_HEADERS([etc/config.h])
AC_CONFIG_SRCDIR([Makefile.global])

AC_ARG_ENABLE(fhs,AC_HELP_STRING([--enable-fhs],[use FHS directories]))

if test "x$prefix" = xNONE && test "x$enable_fhs" = x; then
	AC_MSG_ERROR([Use either --prefix=/installation/path or --enable-fhs])
fi
if test "x$prefix" = xNONE; then
	prefix=$ac_default_prefix
fi

AC_DEFUN([AC_PATH], [
	AC_ARG_WITH($1,
		AS_HELP_STRING([--with-$1],[$2 (default: $3)]),
		[AC_SUBST($1, $withval)],
		[AC_SUBST($1, $3)])
	])

version=`sed -n '3s/^Version //p' ChangeLog`
if test -z "$version"; then
	AC_MSG_ERROR([Could not find version on line 3 of changelog])
fi
AC_SUBST(DOMJUDGE_VERSION, $version)

# {{{ Domserverstuff (webserver, etc)
AC_ARG_WITH(domserver-hostname,,AC_SUBST(domserver_hostname,$withval),
	AC_SUBST(domserver_hostname,domjudge.example.org))

AC_SUBST(domserver_username, domjudge)
AC_SUBST(webserver_group, www-data)

AC_PATH(domserver_root,        [DIR], [$prefix/domserver])
AC_PATH(domserver_bindir,      [DIR], [$domserver_root/bin])
AC_PATH(domserver_etcdir,      [DIR], [$domserver_root/etc])
AC_PATH(domserver_wwwdir,      [DIR], [$domserver_root/www])
AC_PATH(domserver_libdir,      [DIR], [$domserver_root/lib])
AC_PATH(domserver_libwwwdir,   [DIR], [$domserver_root/lib/www])
AC_PATH(domserver_logdir,      [DIR], [$domserver_root/log])
AC_PATH(domserver_tmpdir,      [DIR], [$domserver_root/tmp])
AC_PATH(domserver_wwwtmp,      [DIR], [$domserver_root/wwwtmp])

AC_PATH(judgehost_root,        [DIR], [$prefix/judgehost])
AC_PATH(judgehost_bindir,      [DIR], [$judgehost_root/bin])
AC_PATH(judgehost_etcdir,      [DIR], [$judgehost_root/etc])
AC_PATH(judgehost_judgedir,    [DIR], [$judgehost_root/judging])
AC_PATH(judgehost_libdir,      [DIR], [$judgehost_root/lib])
AC_PATH(judgehost_libjudgedir, [DIR], [$judgehost_root/lib/judge])
AC_PATH(judgehost_logdir,      [DIR], [$judgehost_root/log])

# domserver bits en/disable: use chmod on $domserver_wwwdir/{domserver,public,team}

# }}}

# {{{ submitclient
AC_ARG_WITH([],[],[],[])
AC_ARG_WITH([],[SUBMIT CLIENT],[],[])

with_submitclient_protocols=http,dolstra
AC_ARG_WITH(submitclient-protocols,
[   --with-submitclient-protocols=http,dolstra Protocol list for binary submit
client.  Supported are 'http' and 'dolstra', the first named will be
		default],
[], [])

AC_DEFINE_UNQUOTED(SUBMITCLIENT_METHOD, 0, [foo])

# }}}

# {{{ crap
###AC_ARG_WITH(wwwroot,
###[  --with-wwwroot   Override the wwwroot for website (default: DATAROOTDIR/domjudge/www)]
###,[
###	wwwroot=$withval
###],[
###	eval wwwroot=$datarootdir/domjudge/www
###])
###AC_SUBST(WWWROOT, $wwwroot)
###
###AC_ARG_WITH(wwwlib-path,
###[  --with-wwwlib-path   Override etc-path for website (default: DATAROOTDIR/domjudge)]
###,[
###	wwwlib=$withval
###],[
###	eval wwwlib=$datarootdir/domjudge
###])
###AC_SUBST(WWWLIB_PATH, $wwwlib)
###
###AC_ARG_WITH(wwwetc-path,
###[  --with-wwwetc-path   Override etc-path for website (default: SYSCONFDIR/domjudge)]
###,[
###	wwwetc=$withval
###],[
###	eval wwwetc=$sysconfdir/domjudge
###])
###AC_SUBST(WWWETC_PATH, $wwwetc)
# }}}

AC_CONFIG_FILES([
	Makefile
	bin/Makefile
	etc/Makefile
	judge/Makefile
	lib/Makefile
	submit/Makefile
	www/Makefile

	www/configure.php
	judge/judgedaemon
	test-sources/check-judgings
	etc/domserver-static.php
	etc/domserver-static.h
	etc/judgehost-static.php
	etc/judgehost-config.h
	])
AC_OUTPUT

# vim: fdm=marker
