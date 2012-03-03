# Process this file with autoconf to produce a configure script.
AC_INIT([DOMjudge],
        m4_esyscmd([sed -n '1s/^.*version //p' README | tr -d '\n']),
        [domjudge-devel@lists.A-Eskwadraat.nl])
AC_PREREQ([2.60])
AC_CONFIG_HEADERS([etc/config.h])
AC_CONFIG_SRCDIR([Makefile.global])

# Helper...
AC_DEFUN([AX_WITH_COMMENT], [
	AC_ARG_WITH([ax_comment_$1],[$2],[],[])
	])

AC_SUBST(fhs_enabled,[])
AC_ARG_ENABLE(fhs,AS_HELP_STRING([--enable-fhs],
[use FHS directories (default: no), see below under "Optional
 Packages" for path configuration when FHS is disabled.]),
[if test "x$enableval" != xno ; then AC_SUBST(fhs_enabled,yes) fi])

if test "x$prefix" = xNONE; then
	if test "x$fhs_enabled" = xyes ; then
		prefix=$ac_default_prefix
	else
		prefix="/opt/domjudge"
	fi
fi

if test -z "$PACKAGE_VERSION"; then
	AC_MSG_ERROR([could not find version on first line of README file.])
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

AX_APPEND_COMPILE_FLAGS(-Wall,               DEF_CXFLAGS)
AX_APPEND_COMPILE_FLAGS(-fstack-protector,   DEF_CXFLAGS)
AX_APPEND_COMPILE_FLAGS(-fPIE,               DEF_CXFLAGS)
AX_APPEND_COMPILE_FLAGS(-D_FORTIFY_SOURCE=2, DEF_CXFLAGS)
AX_APPEND_LINK_FLAGS([-fPIE -pie],   DEF_LDFLAGS)
AX_APPEND_LINK_FLAGS([-Wl,-z,relro], DEF_LDFLAGS)
AX_APPEND_LINK_FLAGS([-Wl,-z,now],   DEF_LDFLAGS)

test "x$enable_CFLAGS_setting"   = xyes && AC_SUBST(CFLAGS,   $DEF_CXFLAGS)
test "x$enable_CXXFLAGS_setting" = xyes && AC_SUBST(CXXFLAGS, $DEF_CXFLAGS)
test "x$enable_LDFLAGS_setting"  = xyes && AC_SUBST(LDFLAGS,  $DEF_LDFLAGS)

# {{{ submitclient

def_submitclient_protocols="http"

AC_ARG_ENABLE(submitclient,AS_HELP_STRING([--enable-submitclient@<:@=ARG@:>@],
[enable submit client binary (default: http); set ARG to protocol list
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
AC_SUBST(SUBMIT_ENABLE_WEB, $submit_enablehttp)
AC_SUBST(SUBMIT_ENABLE_CMD, $submit_enabledolstra)
# }}}

# {{{ File ownership for e.g. password files.

AX_WITH_COMMENT(0,[])
AX_WITH_COMMENT(1,[Fine tuning of file ownership:])
AC_MSG_CHECKING([domjudge-user])
AC_ARG_WITH([domjudge-user], [AS_HELP_STRING([--with-domjudge-user=USER],
[User that owns password files (default: current user).])], [], [])

if test "x$with_domjudge_user" = x; then
	AC_SUBST(DOMJUDGE_USER,$USER)
	AC_MSG_RESULT([$DOMJUDGE_USER (default: current user)])
else
	AC_SUBST(DOMJUDGE_USER,$with_domjudge_user)
	AC_MSG_RESULT([$DOMJUDGE_USER])
fi

AC_MSG_CHECKING([webserver-group])
AC_ARG_WITH([webserver-group], [AS_HELP_STRING([--with-webserver-group=GROUP],
[Webserver group for password files (default: www-data).])], [], [])

if test "x$with_webserver_group" = x; then
	AC_SUBST(WEBSERVER_GROUP,"www-data")
	AC_MSG_RESULT([$WEBSERVER_GROUP (default)])
else
	AC_SUBST(WEBSERVER_GROUP,$with_webserver_group)
	AC_MSG_RESULT([$WEBSERVER_GROUP])
fi

# }}}

# {{{ runguard user
AC_MSG_CHECKING([runuser])
AC_ARG_WITH([runuser], [AS_HELP_STRING([--with-runuser=USER],
[Unprivileged user under which to run submissions (default: domjudge-run).])], [], [])

if test "x$with_runuser" = x; then
	AC_SUBST(RUNUSER,"domjudge-run")
	AC_MSG_RESULT($RUNUSER (default))
else
	AC_SUBST(RUNUSER,$with_runuser)
	AC_MSG_RESULT($RUNUSER)
fi
# }}}

# {{{ FHS directory structure

# These are defaults that can still be overridden below!
if test "x$fhs_enabled" = xyes ; then
	AC_SUBST(domserver_root,        '')
	AC_SUBST(domserver_bindir,      $bindir)
	AC_SUBST(domserver_etcdir,      $sysconfdir/${PACKAGE_TARNAME})
	AC_SUBST(domserver_wwwdir,      $datadir/${PACKAGE_TARNAME}/www)
	AC_SUBST(domserver_sqldir,      $datadir/${PACKAGE_TARNAME}/sql)
	AC_SUBST(domserver_libdir,      $libdir/${PACKAGE_TARNAME})
	AC_SUBST(domserver_libextdir,   $libdir/${PACKAGE_TARNAME}/ext)
	AC_SUBST(domserver_libwwwdir,   $libdir/${PACKAGE_TARNAME}/www)
	AC_SUBST(domserver_libsubmitdir,$libdir/${PACKAGE_TARNAME}/submit)
	AC_SUBST(domserver_logdir,      $localstatedir/log/${PACKAGE_TARNAME})
	AC_SUBST(domserver_rundir,      $localstatedir/run/${PACKAGE_TARNAME})
	AC_SUBST(domserver_tmpdir,      /tmp)
	AC_SUBST(domserver_submitdir,   $localstatedir/lib/${PACKAGE_TARNAME}/submissions)

	AC_SUBST(judgehost_root,        '')
	AC_SUBST(judgehost_bindir,      $bindir)
	AC_SUBST(judgehost_etcdir,      $sysconfdir/${PACKAGE_TARNAME})
	AC_SUBST(judgehost_libdir,      $libdir/${PACKAGE_TARNAME})
	AC_SUBST(judgehost_libjudgedir, $libdir/${PACKAGE_TARNAME}/judge)
	AC_SUBST(judgehost_logdir,      $localstatedir/log/${PACKAGE_TARNAME})
	AC_SUBST(judgehost_rundir,      $localstatedir/run/${PACKAGE_TARNAME})
	AC_SUBST(judgehost_tmpdir,      /tmp)
	AC_SUBST(judgehost_judgedir,    $localstatedir/lib/${PACKAGE_TARNAME}/judgings)

	AC_SUBST(domjudge_docdir,       $docdir)
fi

# }}}

# {{{ non-FHS directory structure (the default)
# This is a dirty hack to allow non-standard pathname configuration
# within the autoconf framework.
AC_DEFUN([AX_PATH], [
	AC_ARG_WITH($1,[  --with-$1=DIR   	(default: $2)],
		[AC_SUBST($1, $withval)],
		[if test "x$fhs_enabled" != xyes ; then AC_SUBST($1, $2) fi])
	])

AX_WITH_COMMENT(2,[  ])
AX_WITH_COMMENT(3,[Fine tuning of installation root paths when FHS is disabled (the default):])
AX_PATH(domserver_root,         [$prefix/domserver])
AX_PATH(judgehost_root,         [$prefix/judgehost])
AX_PATH(domjudge_docdir,        [$prefix/doc])
AX_WITH_COMMENT(4,[    ])
AX_WITH_COMMENT(5,[More fine tuning of all installation (sub)directories:])
AX_PATH(domserver_bindir,       [$domserver_root/bin])
AX_PATH(domserver_etcdir,       [$domserver_root/etc])
AX_PATH(domserver_wwwdir,       [$domserver_root/www])
AX_PATH(domserver_sqldir,       [$domserver_root/sql])
AX_PATH(domserver_libdir,       [$domserver_root/lib])
AX_PATH(domserver_libextdir,    [$domserver_root/lib/ext])
AX_PATH(domserver_libwwwdir,    [$domserver_root/lib/www])
AX_PATH(domserver_libsubmitdir, [$domserver_root/lib/submit])
AX_PATH(domserver_logdir,       [$domserver_root/log])
AX_PATH(domserver_rundir,       [$domserver_root/run])
AX_PATH(domserver_tmpdir,       [$domserver_root/tmp])
AX_PATH(domserver_submitdir,    [$domserver_root/submissions])
AX_WITH_COMMENT(6,[     ])
AX_PATH(judgehost_bindir,       [$judgehost_root/bin])
AX_PATH(judgehost_etcdir,       [$judgehost_root/etc])
AX_PATH(judgehost_libdir,       [$judgehost_root/lib])
AX_PATH(judgehost_libjudgedir,  [$judgehost_root/lib/judge])
AX_PATH(judgehost_logdir,       [$judgehost_root/log])
AX_PATH(judgehost_rundir,       [$judgehost_root/run])
AX_PATH(judgehost_tmpdir,       [$judgehost_root/tmp])
AX_PATH(judgehost_judgedir,     [$judgehost_root/judgings])
AX_WITH_COMMENT(7,[      ])

# }}}

# Checks for programs.
AC_PROG_CXX
AC_PROG_CC
AC_PROG_CPP
AC_PROG_INSTALL
AC_PROG_LN_S
AC_PROG_MAKE_SET
AC_PROG_MKDIR_P

# check for htpasswd/htpasswd2
AC_CHECK_PROGS(HTPASSWD,[htpasswd htpasswd2],[no htpasswd binary found])

# Check for including optional libmagic.
AC_CHECK_LIB(magic,magic_open,AC_SUBST(LIBMAGIC,[-lmagic]))

# Checks for libcURL when submit is enabled.
if test "$submit_enablehttp" = "1" ; then
	AX_LIB_CURL([7.9.7],[],[
		AC_MSG_ERROR([libcURL not found (required for http submit method)])
	])
	save_CPPFLAGS="$CPPFLAGS"
	CPPFLAGS="$CPPFLAGS $CURL_CFLAGS"
	AC_CHECK_HEADERS([curl/curl.h],[],[
		AC_MSG_ERROR([libcURL headers not found (required for http submit method)])
	])
	CPPFLAGS="$save_CPPFLAGS"
	# The 'curl-config --static-libs' option is accepted since 7.17.1
	AX_COMPARE_VERSION([$curl_version],[ge],[7.17.1],[
		AC_SUBST(CURL_STATIC,`curl-config --static-libs`)
	],[
		AC_SUBST(CURL_STATIC,"")
	])
fi

# Check for building "checktestdata" program.
AC_ARG_ENABLE(checktestdata,AS_HELP_STRING([--enable-checktestdata],
[enable building of checktestdata program (default: yes).
 This requires the Boost regex and GNU GMP libraries.]))

if test "x$enable_checktestdata" != "xno"; then
	AC_SUBST(CHECKTESTDATA_ENABLED,[yes])

	AX_BOOST_BASE([1.33.1],[],[AC_MSG_ERROR([Boost not available.])])
	AX_BOOST_REGEX

	# Check for gmpxx headers separately, as they are C++.
	AC_LANG_PUSH([C++])
	AC_CHECK_HEADERS([gmpxx.h])
	AC_CHECK_LIB(gmpxx,abs,AC_SUBST(LIBGMPXX,'-lgmp -lgmpxx'),[],[-lgmp])
	AC_LANG_POP([C++])
fi

# See 'man feature_test_macros' under Linux:
# Expose POSIX.1-2001 base specification and SUSv2 features.
# We try to conform to POSIX, so ideally we shouldn't need to include
# other features, but there are still individual files that require
# other specifications.
AC_DEFINE_UNQUOTED(_POSIX_C_SOURCE, 200809L, [Include POSIX.1-2008 base specification])
AC_DEFINE_UNQUOTED(_XOPEN_SOURCE, 500,       [Include SUSv2 (UNIX 98) extensions])

# Checks for header files.
AC_HEADER_STDBOOL
AC_CHECK_HEADERS([fcntl.h netdb.h netinet/in.h stdlib.h string.h sys/ioctl.h sys/param.h sys/socket.h sys/time.h syslog.h termios.h unistd.h magic.h])

# Checks for typedefs, structures, and compiler characteristics.
AC_C_INLINE
AC_TYPE_PID_T
AC_TYPE_SIZE_T
AC_TYPE_SSIZE_T

# Checks for library functions.
dnl FIXME: need to use the results...
AC_FUNC_ERROR_AT_LINE
AC_FUNC_FORK
AC_FUNC_MALLOC
AC_FUNC_REALLOC
AC_FUNC_STRTOD
AC_REPLACE_FUNCS([atexit dup2 getcwd gettimeofday memset mkdir realpath setenv \
                  socket strchr strdup strerror strncasecmp strrchr strstr strtol],
                 [],[AC_MSG_ERROR([required C function is missing.])])

AC_CONFIG_FILES([paths.mk])
AC_OUTPUT

# summary {{{
if test "x$QUIET" = x ; then
echo ""
echo "Summary:"
echo " * project.............: $PACKAGE_NAME $PACKAGE_VERSION"
echo " * prefix..............: $prefix"
echo " * CPPFLAGS............: $CPPFLAGS"
echo " * CFLAGS..............: $CFLAGS"
echo " * CXXFLAGS............: $CXXFLAGS"
echo " * LDFLAGS.............: $LDFLAGS"
echo ""
echo " * default user........: $DOMJUDGE_USER"
echo " * runguard user.......: $RUNUSER"
echo " * webserver group.....: $WEBSERVER_GROUP"
echo ""
if test $SUBMIT_DEFAULT -eq 0 ; then # {{{
	echo " * submitclient........: disabled"
else
	echo -n " * submitclient........: enabled (default: "
	if test $SUBMIT_DEFAULT -eq 1 ; then echo "dolstra)" ; fi
	if test $SUBMIT_DEFAULT -eq 2 ; then echo "http)" ; fi
	if test $SUBMIT_ENABLE_WEB -eq 0 ; then
		echo "    - http.............: no"
	else
		echo "    - http.............: yes, cURL version: $curl_version"
	fi
	if test $SUBMIT_ENABLE_CMD -eq 0 ; then
		echo "    - dolstra..........: no"
	else
		echo "    - dolstra..........: yes"
	fi
fi # }}}
echo ""
echo " * htpasswd............: $HTPASSWD"
echo ""
echo " * documentation.......: $domjudge_docdir"
echo ""
echo " * domserver...........: $domserver_root"
echo "    - bin..............: $domserver_bindir"
echo "    - etc..............: $domserver_etcdir"
echo "    - lib..............: $domserver_libdir"
echo "    - libext...........: $domserver_libextdir"
echo "    - libwww...........: $domserver_libwwwdir"
echo "    - libsubmit........: $domserver_libsubmitdir"
echo "    - log..............: $domserver_logdir"
echo "    - run..............: $domserver_rundir"
echo "    - sql..............: $domserver_sqldir"
echo "    - submit...........: $domserver_submitdir"
echo "    - tmp..............: $domserver_tmpdir"
echo "    - www..............: $domserver_wwwdir"
echo ""
echo " * judgehost...........: $judgehost_root"
echo "    - bin..............: $judgehost_bindir"
echo "    - etc..............: $judgehost_etcdir"
echo "    - lib..............: $judgehost_libdir"
echo "    - libjudge.........: $judgehost_libjudgedir"
echo "    - log..............: $judgehost_logdir"
echo "    - run..............: $judgehost_rundir"
echo "    - tmp..............: $judgehost_tmpdir"
echo "    - judge............: $judgehost_judgedir"
echo ""
echo "Now you might want to run some of:"
echo ""
echo "    make domserver judgehost docs"
echo ""
fi # !QUIET
# }}}

# vim: fdm=marker
