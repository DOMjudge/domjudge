# Process this file with autoconf to produce a configure script.
AC_INIT([DOMjudge],
        m4_esyscmd([sed -n '1s/^.*version //p' README | tr -d '\n']),
        [domjudge-devel@lists.A-Eskwadraat.nl])
AC_CONFIG_HEADERS([etc/config.h])
AC_CONFIG_SRCDIR([Makefile.global])

if test "x$prefix" = xNONE && test "x$enable_fhs" = x; then
	AC_MSG_ERROR([Use either --prefix=/installation/path or --enable-fhs])
fi
if test "x$prefix" = xNONE; then
	prefix=$ac_default_prefix
fi

if test -z "$PACKAGE_VERSION"; then
	AC_MSG_ERROR([Could not find version on first line of README file.])
fi
AC_SUBST(DOMJUDGE_VERSION, $PACKAGE_VERSION)

# {{{ Domserverstuff (webserver, etc)
AC_ARG_WITH(domserver-hostname,,AC_SUBST(domserver_hostname,$withval),
            AC_SUBST(domserver_hostname,domjudge.example.org))

AC_SUBST(domserver_username, domjudge)
AC_SUBST(webserver_group, www-data)

# domserver bits en/disable: use chmod on $domserver_wwwdir/{domserver,public,team}

# }}}

# {{{ non-FHS directory structure (the default)
# This is a dirty hack to allow non-standard pathname configuration
# within the autoconf framework.
AC_DEFUN([AX_PATH], [
	AC_ARG_WITH($1,[  --with-$1=DIR   	(default: $2)],
		[AC_SUBST($1, $withval)],
		[AC_SUBST($1, $2)])
	])
AC_DEFUN([AX_PATH_COMMENT], [
	AC_ARG_WITH(foo$1,[$2],[],[])
	])

AX_PATH_COMMENT(1,[Fine tuning of installation when FHS is disabled (the default):])
AX_PATH(domserver_root,         [$prefix/domserver])
AX_PATH(judgehost_root,         [$prefix/judgehost])
AX_PATH(domjudge_docdir,        [$prefix/doc])
AX_PATH_COMMENT(2,[ ])
AX_PATH(domserver_bindir,       [$domserver_root/bin])
AX_PATH(domserver_etcdir,       [$domserver_root/etc])
AX_PATH(domserver_wwwdir,       [$domserver_root/www])
AX_PATH(domserver_libdir,       [$domserver_root/lib])
AX_PATH(domserver_libwwwdir,    [$domserver_root/lib/www])
AX_PATH(domserver_libsubmitdir, [$domserver_root/lib/submit])
AX_PATH(domserver_logdir,       [$domserver_root/log])
AX_PATH(domserver_tmpdir,       [$domserver_root/tmp])
AX_PATH(domserver_submitdir,    [$domserver_root/submissions])
AX_PATH_COMMENT(3,[  ])
AX_PATH(judgehost_bindir,       [$judgehost_root/bin])
AX_PATH(judgehost_etcdir,       [$judgehost_root/etc])
AX_PATH(judgehost_libdir,       [$judgehost_root/lib])
AX_PATH(judgehost_libjudgedir,  [$judgehost_root/lib/judge])
AX_PATH(judgehost_logdir,       [$judgehost_root/log])
AX_PATH(judgehost_tmpdir,       [$judgehost_root/tmp])
AX_PATH(judgehost_judgedir,     [$judgehost_root/judgings])
AX_PATH_COMMENT(4,[  ])

# }}}

# {{{ FHS directory structure
AC_ARG_ENABLE(fhs,AS_HELP_STRING([--enable-fhs],
[use FHS directories (default: no), see below under "Optional
 Packages" for path configuration when FHS is disabled.]))

if test "x$enable_fhs" = xyes ; then
	AC_SUBST(domserver_bindir,      $bindir)
	AC_SUBST(domserver_etcdir,      $sysconfdir)
	AC_SUBST(domserver_wwwdir,      $datadir/${PACKAGE_TARNAME}/www)
	AC_SUBST(domserver_libdir,      $libdir/${PACKAGE_TARNAME})
	AC_SUBST(domserver_libsubmitdir,$libdir/${PACKAGE_TARNAME}/submit)
	AC_SUBST(domserver_logdir,      $localstatedir/log/${PACKAGE_TARNAME})
	AC_SUBST(domserver_tmpdir,      /tmp)
	AC_SUBST(domserver_submitdir,   $localstatedir/lib/${PACKAGE_TARNAME}/submissions)

	AC_SUBST(judgehost_bindir,      $bindir)
	AC_SUBST(judgehost_etcdir,      $sysconfdir)
	AC_SUBST(judgehost_libdir,      $libdir/${PACKAGE_TARNAME})
	AC_SUBST(judgehost_libjudgedir, $libdir/${PACKAGE_TARNAME}/judge)
	AC_SUBST(judgehost_logdir,      $localstatedir/log/${PACKAGE_TARNAME})
	AC_SUBST(judgehost_tmpdir,      /tmp)
	AC_SUBST(judgehost_judgedir,    $localstatedir/lib/${PACKAGE_TARNAME}/judgings)

	AC_SUBST(domjudge_docdir,       $docdir)
fi

# }}}

# {{{ submitclient

def_submitclient_protocols="http,dolstra"

AC_ARG_ENABLE(submitclient,AS_HELP_STRING([--enable-submitclient@<:@=ARG@:>@],
[enable submit client binary (default: yes); set ARG to protocol list
to support, where the first will be default. Supported protocols are
in default order 'http,dolstra'.]),
[
if test "x$enableval" = xyes ; then
	submitclient_protocols=$def_submitclient_protocols
elif test "x$enableval" = xno ; then
	submitclient_protocols=''
else
	submitclient_protocols="$enableval"
fi
],
[submitclient_protocols=$def_submitclient_protocols])

submit_default=0
case `echo "x$submitclient_protocols" | sed 's/,.*//'` in
	xdolstra) submit_default=1 ;;
	xhttp)    submit_default=2 ;;
	*)        submit_default=0 ;;
esac

submit_enabledolstra=0
submit_enablehttp=0
for i in `echo "$submitclient_protocols" | sed 's/,/ /g'` ; do
    eval submit_enable$i=1
done

AC_SUBST(          SUBMIT_DEFAULT, $submit_default)
AC_DEFINE_UNQUOTED(SUBMIT_DEFAULT, $submit_default,
[Default submission protocol: 1 = 'dolstra', 2 = 'http'])

AC_SUBST(          SUBMIT_ENABLE_CMD, $submit_enabledolstra)
AC_DEFINE_UNQUOTED(SUBMIT_ENABLE_CMD, $submit_enabledolstra,
[Enable submission via 'dolstra' protocol.])

AC_SUBST(          SUBMIT_ENABLE_WEB, $submit_enablehttp)
AC_DEFINE_UNQUOTED(SUBMIT_ENABLE_WEB, $submit_enablehttp,
[Enable submission via 'http' protocol.])

# }}}

# Checks for programs.
AC_PROG_CXX
AC_PROG_CC
AC_PROG_CPP
#AC_PROG_INSTALL
AC_PROG_LN_S
AC_PROG_MAKE_SET

AX_WITH_PROG(BEEP,beep,[none])
if test "x$BEEP" = "xnone" ; then
	AC_MSG_WARN([command `beep' not available])
else
	AC_DEFINE_UNQUOTED(BEEP_CMD,"$BEEP",[Path of the `beep' command.])
fi

# Checks for libraries.
AC_CHECK_LIB(magic,magic_open,AC_SUBST(libmagic,[-lmagic]))

# Checks for header files.
AC_HEADER_STDC
AC_HEADER_SYS_WAIT
AC_CHECK_HEADERS([fcntl.h netdb.h netinet/in.h stdlib.h string.h sys/ioctl.h sys/param.h sys/socket.h sys/time.h syslog.h termios.h unistd.h magic.h])

# Checks for typedefs, structures, and compiler characteristics.
AC_C_CONST
AC_TYPE_PID_T
AC_TYPE_SIZE_T
AC_TYPE_SSIZE_T
AC_HEADER_TIME
AC_HEADER_STDBOOL

# Checks for library functions.
AC_FUNC_ERROR_AT_LINE
AC_FUNC_FORK
AC_PROG_GCC_TRADITIONAL
AC_FUNC_MALLOC
AC_FUNC_SETVBUF_REVERSED
AC_TYPE_SIGNAL
AC_FUNC_STAT
AC_FUNC_STRFTIME
AC_FUNC_VPRINTF
AC_CHECK_FUNCS([alarm dup2 getcwd gettimeofday memset mkdir realpath setenv socket strchr strdup strerror strncasecmp strrchr strstr strtol])

AC_CONFIG_FILES([
	paths.mk
	etc/Makefile
	submit/Makefile

	www/configure.php
	etc/domserver-static.php
	etc/domserver-static.h
	etc/judgehost-static.php
	etc/judgehost-config.h
	])
AC_OUTPUT

# vim: fdm=marker
