# Process this file with autoconf to produce a configure script.
AC_INIT([DOMjudge],
        m4_esyscmd([grep 'version' README.md | sed -n '1s/^.*version //p' | tr -d '\n']),
        [domjudge-devel@domjudge.org])
AC_PREREQ([2.64])
AC_CONFIG_HEADERS([etc/config.h])
AC_CONFIG_SRCDIR([Makefile.global])

# Helper...
AC_DEFUN([AX_WITH_COMMENT], [
	AC_ARG_WITH([ax_comment_$1],[$2],[],[])
	])

AC_SUBST(FHS_ENABLED,no)
AC_ARG_ENABLE(fhs,AS_HELP_STRING([--enable-fhs],
[use FHS directories (default: no), see below under "Optional
 Packages" for path configuration when FHS is disabled.]),
[if test "x$enableval" != xno ; then AC_SUBST(FHS_ENABLED,yes) fi])

if test "x$prefix" = xNONE; then
	if test "x$FHS_ENABLED" = xyes ; then
		prefix=$ac_default_prefix
	else
		prefix="/opt/domjudge"
	fi
fi

if test -z "$PACKAGE_VERSION"; then
	AC_MSG_ERROR([could not find version on first line of README.md file.])
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

# {{{ File ownership for e.g. password files.

AX_WITH_COMMENT(0,[])
AX_WITH_COMMENT(1,[Fine tuning of file ownership:])
AC_MSG_CHECKING([domjudge-user])
AC_ARG_WITH([domjudge-user], [AS_HELP_STRING([--with-domjudge-user=USER],
[User that owns password files (default: current user).])], [], [])

if test "x$with_domjudge_user" = x; then
	# Check and warn if running as root (without explicitly setting it):
	if test "x$USER" = xroot; then
		AC_MSG_ERROR([installing/running as root is STRONGLY DISCOURAGED, use --with-domjudge-user=root to override.])
	fi

	AC_SUBST(DOMJUDGE_USER,$USER)
	AC_MSG_RESULT([$DOMJUDGE_USER (default: current user)])
else
	AC_SUBST(DOMJUDGE_USER,$with_domjudge_user)
	AC_MSG_RESULT([$DOMJUDGE_USER])
fi

AC_MSG_CHECKING([webserver-group])
AC_ARG_WITH([webserver-group], [AS_HELP_STRING([--with-webserver-group=GROUP],
[Webserver group for password files (default: try to detect).])], [], [])

if test "x$with_webserver_group" = x; then
	# Try a number of group names and choose first one found
	found=0
	for g in www-data apache httpd ; do
		if getent group $g >/dev/null 2>&1 ; then
		   found=1
		   break
		fi
	done
	if test $found -ne 0 ; then
		AC_SUBST(WEBSERVER_GROUP,"$g")
		AC_MSG_RESULT([$WEBSERVER_GROUP (detected)])
	else
		AC_MSG_ERROR([webserver group could not be detected, use --with-webserver-group=GROUP)])
	fi
else
	AC_SUBST(WEBSERVER_GROUP,$with_webserver_group)
	AC_MSG_RESULT([$WEBSERVER_GROUP])
fi

# }}}

# {{{ runguard
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

AC_MSG_CHECKING([rungroup])
AC_ARG_WITH([rungroup], [AS_HELP_STRING([--with-rungroup=GROUP],
[Unprivileged group under which to run submissions (default: same as runuser).])], [], [])

if test "x$with_rungroup" = x; then
	AC_SUBST(RUNGROUP,"$RUNUSER")
	AC_MSG_RESULT($RUNGROUP (default))
else
	AC_SUBST(RUNGROUP,$with_rungroup)
	AC_MSG_RESULT($RUNGROUP)
fi

# Check for using Linux cgroups for memory control
AC_CHECK_LIB(cgroup, cgroup_init, AC_SUBST(LIBCGROUP,[-lcgroup]), AC_MSG_ERROR([Linux cgroup library not found.]))

# }}}

# {{{ FHS directory structure

# These are defaults that can still be overridden below!
if test "x$FHS_ENABLED" = xyes ; then
	AC_SUBST(domserver_root,        '')
	AC_SUBST(domserver_bindir,      $bindir)
	AC_SUBST(domserver_etcdir,      $sysconfdir/${PACKAGE_TARNAME})
	AC_SUBST(domserver_webappdir,   $datadir/${PACKAGE_TARNAME}/webapp)
	AC_SUBST(domserver_sqldir,      $datadir/${PACKAGE_TARNAME}/sql)
	AC_SUBST(domserver_libdir,      $libdir/${PACKAGE_TARNAME})
	AC_SUBST(domserver_libvendordir,$libdir/${PACKAGE_TARNAME}/vendor)
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
	AC_SUBST(judgehost_chrootdir,   /chroot/${PACKAGE_TARNAME})
	AC_SUBST(judgehost_cgroupdir,   /sys/fs/cgroup)

	AC_SUBST(domjudge_docdir,       $docdir)
fi

# }}}

# {{{ non-FHS directory structure (the default)
# This is a dirty hack to allow non-standard pathname configuration
# within the autoconf framework.
AC_DEFUN([AX_PATH], [
	AC_ARG_WITH($1,[  --with-$1=DIR   	(default: $2)],
		[AC_SUBST($1, $withval)],
		[if test "x$FHS_ENABLED" != xyes ; then AC_SUBST($1, $2) fi])
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
AX_PATH(domserver_webappdir,    [$domserver_root/webapp])
AX_PATH(domserver_sqldir,       [$domserver_root/sql])
AX_PATH(domserver_libdir,       [$domserver_root/lib])
AX_PATH(domserver_libvendordir, [$domserver_root/lib/vendor])
AX_PATH(domserver_logdir,       [$domserver_root/log])
AX_PATH(domserver_rundir,       [$domserver_root/run])
AX_PATH(domserver_tmpdir,       [$domserver_root/tmp])
AX_PATH(domserver_submitdir,    [$domserver_root/submissions])
AX_WITH_COMMENT(6,[     ])
AX_PATH(judgehost_bindir,       [$judgehost_root/bin])
AX_PATH(judgehost_etcdir,       [$judgehost_root/etc])
AX_PATH(judgehost_libdir,       [$judgehost_root/lib])
AX_PATH(judgehost_libvendordir, [$judgehost_root/lib/vendor])
AX_PATH(judgehost_libjudgedir,  [$judgehost_root/lib/judge])
AX_PATH(judgehost_logdir,       [$judgehost_root/log])
AX_PATH(judgehost_rundir,       [$judgehost_root/run])
AX_PATH(judgehost_tmpdir,       [$judgehost_root/tmp])
AX_PATH(judgehost_judgedir,     [$judgehost_root/judgings])
AX_PATH(judgehost_chrootdir,    [/chroot/${PACKAGE_TARNAME}])
AX_PATH(judgehost_cgroupdir,    [/sys/fs/cgroup])
AX_WITH_COMMENT(7,[      ])

# }}}

AC_MSG_CHECKING([baseurl])
AC_ARG_WITH([baseurl], [AS_HELP_STRING([--with-baseurl=URL],
[Base URL of the DOMjudge web interfaces. Example default:
 "https://example.com/domjudge/".])], [], [])
AX_WITH_COMMENT(8,[       ])

if test "x$with_baseurl" != x; then
	AC_SUBST(BASEURL,$with_baseurl)
	AC_MSG_RESULT($BASEURL)
else
	AC_SUBST(BASEURL,'https://example.com/domjudge/')
	AC_SUBST(BASEURL_UNCONFIGURED,1)
fi

# Checks for programs.
AC_PROG_CXX
AC_PROG_CC
AC_PROG_CXXCPP
AC_PROG_CPP
AC_PROG_INSTALL
AC_PROG_LN_S
AC_PROG_MAKE_SET
AC_PROG_MKDIR_P

# Check for including optional libmagic.
AC_CHECK_LIB(magic,magic_open,AC_SUBST(LIBMAGIC,[-lmagic]))

# {{{ submitclient

AC_ARG_ENABLE(submitclient,AS_HELP_STRING([--disable-submitclient],
[enable submit client program (default: yes).
 This requires JSONcpp and cURL libraries and optionally
 libmagic for detecting submission of binary files.]))

if test "x$enable_submitclient" != "xno"; then
	AC_SUBST(SUBMITCLIENT_ENABLED,[yes])

	# Check for libcURL and JSONcpp when submit is enabled.
	AX_LIB_CURL([7.9.7],[],[
		AC_MSG_ERROR([libcURL not found (required for submit client)])
	])
	save_CPPFLAGS="$CPPFLAGS"
	CPPFLAGS="$CPPFLAGS $CURL_CFLAGS"
	AC_CHECK_HEADERS([curl/curl.h],[],[
		AC_MSG_ERROR([libcURL headers not found (required for submit client)])
	])
	CPPFLAGS="$save_CPPFLAGS"
	# The 'curl-config --static-libs' option is accepted since 7.17.1
	AC_SUBST(CURL_STATIC,"")
	if test "x$enable_static_linking" = "xyes"; then
		AX_COMPARE_VERSION([$curl_version],[ge],[7.17.1],[
			AC_SUBST(CURL_STATIC,`curl-config --static-libs`)])
	fi

	# Check for JSONcpp library to decode language/extensions list from
	# REST API (this also requires libcURL below).

	# Note that under Debian the header files are installed under
	# PREFIX/jsoncpp/json/json.h and not PREFIX/json/json.h since that
	# conflicts with the libjson C library, so we check that first.
	AC_LANG_PUSH([C++])
	AC_CHECK_HEADERS([jsoncpp/json/json.h json/json.h],[found_jsoncpp_headers=yes; break;],[])
	AS_IF([test "x$found_jsoncpp_headers" != "xyes"],
		AC_MSG_ERROR([JSONcpp headers not found (required for submit client)]),
		AC_SUBST(LIBJSONCPP,[-ljsoncpp]))
	AC_LANG_POP([C++])

fi
# }}}

# Check option to link statically against "special" libraries for
# submit client.
AC_ARG_ENABLE(static-linking,AS_HELP_STRING([--enable-static-linking],
[enable static linking against libcurl, libmagic, libjsoncpp for 'submit'
 (default: no).]))

if test "x$enable_static_linking" = "xyes"; then
	AC_SUBST(STATIC_LINK_START,[-Wl,-Bstatic])
	AC_SUBST(STATIC_LINK_END,  [-Wl,-Bdynamic])
else
	AC_SUBST(STATIC_LINK_START,[''])
	AC_SUBST(STATIC_LINK_END,  [''])
fi

# Check option to build documentation. This option is provided to
# allow disabling it when a LaTeX installation is not present.
AC_ARG_ENABLE(doc-build,AS_HELP_STRING([--enable-doc-build],
[enable building documentation; requires a working LaTeX installation
 for 'make dist' (default: yes).]))

if test "x$enable_doc_build" = "xno"; then
	AC_SUBST(DOC_BUILD_ENABLED,[no])
else
	AC_SUBST(DOC_BUILD_ENABLED,[yes])
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
AC_CHECK_HEADERS([fcntl.h stdlib.h string.h sys/param.h sys/time.h syslog.h termios.h unistd.h magic.h libcgroup.h])

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
echo " * runguard group......: $RUNGROUP"
echo " * webserver group.....: $WEBSERVER_GROUP"
echo ""
echo " * website base URL....: $BASEURL"
echo ""
if test "x$SUBMITCLIENT_ENABLED" = xyes ; then
   echo " * submitclient........: enabled, cURL version: $curl_version"
else
   echo " * submitclient........: disabled"
fi
echo ""
echo -n " * documentation.......: $domjudge_docdir"
if test "x$DOC_BUILD_ENABLED" != xyes ; then
   echo " (disabled)"
else
   echo ""
fi
echo ""
echo " * domserver...........: $domserver_root"
echo "    - bin..............: $domserver_bindir"
echo "    - etc..............: $domserver_etcdir"
echo "    - lib..............: $domserver_libdir"
echo "    - libvendor........: $domserver_libvendordir"
echo "    - log..............: $domserver_logdir"
echo "    - run..............: $domserver_rundir"
echo "    - sql..............: $domserver_sqldir"
echo "    - submit...........: $domserver_submitdir"
echo "    - tmp..............: $domserver_tmpdir"
echo "    - webapp...........: $domserver_webappdir"
echo ""
echo " * judgehost...........: $judgehost_root"
echo "    - bin..............: $judgehost_bindir"
echo "    - etc..............: $judgehost_etcdir"
echo "    - lib..............: $judgehost_libdir"
echo "    - libvendor........: $judgehost_libvendordir"
echo "    - libjudge.........: $judgehost_libjudgedir"
echo "    - log..............: $judgehost_logdir"
echo "    - run..............: $judgehost_rundir"
echo "    - tmp..............: $judgehost_tmpdir"
echo "    - judge............: $judgehost_judgedir"
echo "    - chroot...........: $judgehost_chrootdir"
echo "    - cgroup...........: $judgehost_cgroupdir"
echo ""
echo "Run 'make' without arguments to get a list of (build) targets."
echo ""
if test "x$BASEURL_UNCONFIGURED" = x1 ; then
   echo "Warning: base URL is unconfigured; the submit client, generating team"
   echo "documentation and OpenID support will not work out of the box!"
   echo "Rerun configure with option '--with-baseurl=BASEURL' to correct this."
   echo ""
fi
fi # !QUIET
# }}}

# vim: fdm=marker
