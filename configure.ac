AC_INIT([domjudge], [UNKNOWN], [domjudge@A-Eskwadraat.nl])
AC_CONFIG_HEADERS([etc/config.h])

AC_ARG_ENABLE(fhs,AC_HELP_STRING([--enable-fhs],[use FHS directories]))

if test "x$prefix" = xNONE && test "x$enable_fhs" = x; then
	AC_MSG_ERROR([Use either --prefix=/installation/path or --enable-fhs])
fi
if test "x$prefix" = xNONE; then
	prefix=$ac_default_prefix
fi

AC_DEFUN([AC_PATH], [
	AC_ARG_WITH($1-path,
		AC_HELP_STRING([--with-$1-path],[$2 (default: $3)]),
		[AC_SUBST($1_path, $withval)],
		[AC_SUBST($1_path, $3)])
	])

version=`sed -n '3s/^Version //p' ChangeLog`
if test -z "$version"; then
	AC_MSG_ERROR([Could not find version on line 3 of changelog])
fi
AC_SUBST(DOMJUDGE_VERSION, $version)

# {{{ Jurystuff (webserver, etc)
AC_ARG_WITH(jury-hostname,,AC_SUBST(jury_hostname,$withval),
	AC_SUBST(jury_hostname,domjudge.example.org))

AC_SUBST(juryusername, domjudge)
AC_SUBST(webservergroup, www-data)

AC_PATH(juryroot, [foo bar], [$prefix/jury])
AC_PATH(juryconf, [foo bar], [$juryroot_path/etc])
AC_PATH(jurywww, [foo bar], [$juryroot_path/www])
AC_PATH(jurylib, [foo bar], [$juryroot_path/lib])
AC_PATH(jurybin, [foo bar], [$juryroot_path/bin])
AC_PATH(jurylog, [foo bar], [$juryroot_path/log])
AC_PATH(jurytmp, [foo bar], [$juryroot_path/tmp])
AC_PATH(jurywwwtmp, [foo bar], [$juryroot_path/wwwtmp])

# Jury bits en/disable: use chmod on $jurywww_path/{jury,public,team}

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
	etc/Makefile
	www/Makefile

	www/configure.php
	])
AC_OUTPUT

# vim: fdm=marker
