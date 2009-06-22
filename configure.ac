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
AC_DEFINE_UNQUOTED(DOMJUDGE_VERSION, "$PACKAGE_VERSION",
[DOMjudge version number (alias for PACKAGE_VERSION)])

# Set default {C,CXX,LD}FLAGS. This might screw up portability, but
# adds important security. Only set these flags when none are supplied
# by the user.
for flag in CFLAGS CXXFLAGS LDFLAGS ; do
	AC_MSG_CHECKING([whether configure should try to set $flag])
	if test x`eval echo '${'$flag'+set}'` = xset ; then res=no ; else res=yes ; fi
	eval enable_${flag}_setting=$res
	AC_MSG_RESULT($res)
done

DEF_CXFLAGS="-g -O2"
DEF_LDFLAGS=""

AX_CHECK_COMPILER_FLAGS(-Wall,             DEF_CXFLAGS="$DEF_CXFLAGS -Wall")
AX_CHECK_COMPILER_FLAGS(-fstack-protector, DEF_CXFLAGS="$DEF_CXFLAGS -fstack-protector")
AX_CHECK_COMPILER_FLAGS(-fPIE,             DEF_CXFLAGS="$DEF_CXFLAGS -fPIE"
                                           DEF_LDFLAGS="$DEF_LDFLAGS -pie")

test "x$enable_CFLAGS_setting"   = xyes && AC_SUBST(CFLAGS,   $DEF_CXFLAGS)
test "x$enable_CXXFLAGS_setting" = xyes && AC_SUBST(CXXFLAGS, $DEF_CXFLAGS)
test "x$enable_LDFLAGS_setting"  = xyes && AC_SUBST(LDFLAGS,  $DEF_LDFLAGS)

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

AC_MSG_CHECKING(submitclient)
submit_default=0
case `echo "x$submitclient_protocols" | sed 's/,.*//'` in
	xdolstra) submit_default=1; AC_MSG_RESULT([enabled (dolstra)]) ;;
	xhttp)    submit_default=2; AC_MSG_RESULT([enabled (http)])  ;;
	*)        submit_default=0; AC_MSG_RESULT([disabled]) ;;
esac

submit_enabledolstra=0
submit_enablehttp=0
for i in `echo "$submitclient_protocols" | sed 's/,/ /g'` ; do
    eval submit_enable$i=1
done

AC_SUBST(SUBMIT_DEFAULT,    $submit_default)
AC_SUBST(SUBMIT_ENABLE_CMD, $submit_enabledolstra)
AC_SUBST(SUBMIT_ENABLE_WEB, $submit_enablehttp)
# }}}

# {{{ File ownership for e.g. password files.

AC_MSG_CHECKING([domjudge_user])
AC_ARG_ENABLE([domjudge_user], [AS_HELP_STRING([--enable-domjudge_user@<:@=USER@:>@], [User that owns password files (default: current user).])], [], [])

if test "x$enableval" = x; then
	AC_SUBST(DOMJUDGE_USER,$USER)
	AC_MSG_RESULT([$DOMJUDGE_USER (default: current user)])
else
	AC_SUBST(DOMJUDGE_USER,$enableval)
	AC_MSG_RESULT([$DOMJUDGE_USER])
fi

AC_MSG_CHECKING([webserver_group])
AC_ARG_ENABLE([webserver_group], [AS_HELP_STRING([--with-webserver_group@<:@=GROUP@:>@], [Webserver group for password files (default: www-data).])], [], [])

if test "x$enableval" = x; then
	AC_SUBST(WEBSERVER_GROUP,"www-data")
	AC_MSG_RESULT([$WEBSERVER_GROUP (default)])
else
	AC_SUBST(WEBSERVER_GROUP,$enableval)
	AC_MSG_RESULT([$WEBSERVER_GROUP])
fi

# }}}

# {{{ runguard user
AC_MSG_CHECKING([runuser])
AC_ARG_ENABLE([runuser], [AS_HELP_STRING([--enable-runuser@<:@=USER@:>@], [Unprivileged user under which to run submissions (default: domjudge-run).])], [], [])

if test "x$enableval" = x; then
	AC_SUBST(RUNUSER,"domjudge-run")
	AC_MSG_RESULT($RUNUSER (default))
else
	AC_SUBST(RUNUSER,$enableval)
	AC_MSG_RESULT($RUNUSER)
fi
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

AX_PATH_COMMENT(0,[])
AX_PATH_COMMENT(1,[Fine tuning of installation when FHS is disabled (the default):])
AX_PATH(domserver_root,         [$prefix/domserver])
AX_PATH(judgehost_root,         [$prefix/judgehost])
AX_PATH(domjudge_docdir,        [$prefix/doc])
AX_PATH_COMMENT(2,[ ])
AX_PATH(domserver_bindir,       [$domserver_root/bin])
AX_PATH(domserver_etcdir,       [$domserver_root/etc])
AX_PATH(domserver_wwwdir,       [$domserver_root/www])
AX_PATH(domserver_sqldir,       [$domserver_root/sql])
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

# }}}

# {{{ FHS directory structure
AC_ARG_ENABLE(fhs,AS_HELP_STRING([--enable-fhs],
[use FHS directories (default: no), see below under "Optional
 Packages" for path configuration when FHS is disabled.]))

if test "x$enable_fhs" = xyes ; then
	AC_SUBST(domserver_bindir,      $bindir)
	AC_SUBST(domserver_etcdir,      $sysconfdir/${PACKAGE_TARNAME})
	AC_SUBST(domserver_wwwdir,      $datadir/${PACKAGE_TARNAME}/www)
	AC_SUBST(domserver_sqldir,      $datadir/${PACKAGE_TARNAME}/sql)
	AC_SUBST(domserver_libdir,      $libdir/${PACKAGE_TARNAME})
	AC_SUBST(domserver_libwwwdir,   $libdir/${PACKAGE_TARNAME}/www)
	AC_SUBST(domserver_libsubmitdir,$libdir/${PACKAGE_TARNAME}/submit)
	AC_SUBST(domserver_logdir,      $localstatedir/log/${PACKAGE_TARNAME})
	AC_SUBST(domserver_tmpdir,      /tmp)
	AC_SUBST(domserver_submitdir,   $localstatedir/lib/${PACKAGE_TARNAME}/submissions)

	AC_SUBST(judgehost_bindir,      $bindir)
	AC_SUBST(judgehost_etcdir,      $sysconfdir/${PACKAGE_TARNAME})
	AC_SUBST(judgehost_libdir,      $libdir/${PACKAGE_TARNAME})
	AC_SUBST(judgehost_libjudgedir, $libdir/${PACKAGE_TARNAME}/judge)
	AC_SUBST(judgehost_logdir,      $localstatedir/log/${PACKAGE_TARNAME})
	AC_SUBST(judgehost_tmpdir,      /tmp)
	AC_SUBST(judgehost_judgedir,    $localstatedir/lib/${PACKAGE_TARNAME}/judgings)

	AC_SUBST(domjudge_docdir,       $docdir)
fi

# }}}

# Checks for programs.
AC_PROG_CXX
AC_PROG_CC
AC_PROG_CPP
#AC_PROG_INSTALL
AC_PROG_LN_S
AC_PROG_MAKE_SET

# Checks for libraries.
AC_CHECK_LIB(magic,magic_open,AC_SUBST(LIBMAGIC,[-lmagic]))

if test "$submit_enablehttp" = "1" ; then
	AC_CHECK_CURL([7.9.7])
 	AC_CHECK_HEADERS([curl/curl.h])
fi

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

AC_CONFIG_FILES([paths.mk])
AC_OUTPUT

# vim: fdm=marker
