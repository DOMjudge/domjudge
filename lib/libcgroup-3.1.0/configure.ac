#                                               -*- Autoconf -*-
# SPDX-License-Identifier: LGPL-2.1-only
# Process this file with autoconf to produce a configure script.
#
#  Copyright International Business Machines Corp. 2008
#
#  Authors:	Balbir Singh <balbir@linux.vnet.ibm.com>
#
AC_PREREQ([2.69])

# In following section update all occurences of version, including soname
AC_INIT([libcgroup],[3.1.0])

AC_CONFIG_AUX_DIR([build-aux])
AC_CONFIG_MACRO_DIRS([m4])

AM_INIT_AUTOMAKE([foreign dist-bzip2 subdir-objects])

m4_ifdef([AM_SILENT_RULES], [AM_SILENT_RULES([yes])])

# set library version, soname is libcgroup.so.MAJOR
AC_SUBST(LIBRARY_VERSION_MAJOR, 3)
AC_SUBST(LIBRARY_VERSION_MINOR, 1)
AC_SUBST(LIBRARY_VERSION_RELEASE, 0)

# we do not want static libraries
#AC_DISABLE_STATIC

AM_PROG_AR
LT_INIT

AC_CONFIG_SRCDIR([src])
AC_CONFIG_HEADER([config.h])

# Process command line options
AC_ARG_ENABLE([tools],
	[AS_HELP_STRING([--enable-tools],[compile libcgroup tools [default=yes]])],
	[
		if test "x$enableval" = xno; then
			with_tools=false
		else
			with_tools=true
		fi
	],
	[with_tools=true])
AM_CONDITIONAL([WITH_TOOLS], [test x$with_tools = xtrue])

AC_ARG_ENABLE([pam],
	[AS_HELP_STRING([--enable-pam],[compile libcgroup PAM module [default=yes]])],
	[
		if test "x$enableval" = xno; then
			with_pam=false
		else
			with_pam=true
		fi
	],
	[with_pam=true])
AM_CONDITIONAL([WITH_PAM], [test x$with_pam = xtrue])

AC_ARG_ENABLE([daemon],
	[AS_HELP_STRING([--enable-daemon],[compile libcgroup daemon [default=yes]])],
	[
		if test "x$enableval" = xno; then
			with_daemon=false
		else
			with_daemon=true
		fi
	],
	[with_daemon=true])
AM_CONDITIONAL([WITH_DAEMON], [test x$with_daemon = xtrue])

AC_ARG_ENABLE([python],
	[AS_HELP_STRING([--enable-python],
	[build the python bindings, requires cython])])
AS_IF([test "$enable_python" = yes], [
	# cython version check
	AS_IF([test "$CYTHON_VER_MAJ" -eq 0 -a "$CYTHON_VER_MIN" -lt 29], [
		AC_MSG_ERROR([python bindings require cython 0.29 or higher])
	])
	AM_PATH_PYTHON([3])
])
AM_CONDITIONAL([ENABLE_PYTHON], [test "$enable_python" = yes])
AC_DEFINE_UNQUOTED([ENABLE_PYTHON],
	[$(test "$enable_python" = yes && echo 1 || echo 0)],
	[Python bindings build flag.])

AC_ARG_ENABLE([systemd],
	[AS_HELP_STRING([--enable-systemd],[enable systemd support [default=yes]])],
	[
		if test "x$enableval" = xno; then
			with_systemd=false
		else
			with_systemd=true
		fi
	],
	[with_systemd=true])
AM_CONDITIONAL([WITH_SYSTEMD], [test x$with_systemd = xtrue])

AC_ARG_ENABLE([initscript-install],
	[AS_HELP_STRING([--enable-initscript-install],[install init scripts [default=no]])],
	[
		if test "x$enableval" = xno; then
			with_initscript_install=false
		else
			with_initscript_install=true
		fi
	],
	[with_initscript_install=false])
AM_CONDITIONAL([WITH_INITSCRIPT_INSTALL], [test x$with_initscript_install = xtrue])

socket_path="/var/run/cgred.socket"
AC_ARG_ENABLE([cgred-socket],
	[AS_HELP_STRING([--enable-cgred-socket=PATH],[specify location of cgrulesengd communication socket
		(default=/var/run/cgred.socket)])],
	[
		if test "x$enableval" = xno -o "x$enableval" = xyes; then
			AC_MSG_ERROR([Provide valid path with --enable-cgred-socket option.])
		else
			socket_path="$enableval"
		fi
	], [])
AC_DEFINE_UNQUOTED([CGRULE_CGRED_SOCKET_PATH],"$socket_path", [Cgrulesengd socket path])

pam_module_dir="$libdir/security"
AC_ARG_ENABLE([pam-module-dir],
	[AS_HELP_STRING([--enable-pam-module-dir=PATH],[specify location of libcgroup PAM module
		(default=$libdir/security)])],
	[
		if test "x$enableval" = xno -o "x$enableval" = xyes; then
			AC_MSG_ERROR([Provide valid path with --enable-pam-module-dir option.])
		else
			pam_module_dir="$enableval"
		fi
	], [])
AC_SUBST([pamlibdir],"$pam_module_dir")

AC_ARG_ENABLE([opaque-hierarchy],
	[AS_HELP_STRING([--enable-opaque-hierarchy=NAME],[specify name of a hierarchy which libcgroup should ignore, e.g. name=systemd
		(default=none)])],
	[
		if test "x$enableval" = xno -o "x$enableval" = xyes; then
			AC_MSG_ERROR([Provide name of a hierarchy.])
		else
			AC_DEFINE_UNQUOTED([OPAQUE_HIERARCHY], "$enableval",
				[Define to ignore specific hierarchy.])
		fi
	], [])

AC_ARG_ENABLE([tests],
      [AS_HELP_STRING([--enable-tests],[compile libcgroup tests [default=yes]])],
      [
		if test "x$enableval" = xno; then
			with_tests=false
		else
			with_tests=true
		fi
       ],
       [with_tests=true])
AM_CONDITIONAL([WITH_TESTS], [test x$with_tests = xtrue])

AC_ARG_ENABLE([samples],
      [AS_HELP_STRING([--enable-samples],[compile libcgroup samples C programs [default=no]])],
      [
		if test "x$enableval" = xno; then
			with_samples=false
		else
			with_samples=true
		fi
       ],
       [with_samples=false])
AM_CONDITIONAL([WITH_SAMPLES], [test x$with_samples = xtrue])

# Checks for programs.
AC_PROG_CXX
AC_PROG_CC
AC_PROG_YACC
if test "$YACC" = yacc; then
    AC_CHECK_PROG([REALLY_YACC], [yacc], [yacc])
    if test "$REALLY_YACC" = ""; then
        AC_MSG_ERROR([This program cannot be built unless a version of yacc is installed.])
    fi
fi
AM_PROG_LEX
if test "$LEX" != flex; then
    AC_MSG_ERROR([This program cannot be built unless flex is installed.])
fi
LT_INIT

# Checks for header files.
AC_HEADER_DIRENT
AC_HEADER_STDC
AC_CHECK_HEADERS([limits.h mntent.h stdlib.h string.h sys/mount.h unistd.h])

# Checks for typedefs, structures, and compiler characteristics.
AC_HEADER_STDBOOL
AC_C_CONST
AC_TYPE_UID_T
AC_C_INLINE
AC_TYPE_INT64_T
AC_TYPE_PID_T

# Checks for library functions.
AC_FUNC_CHOWN
AC_FUNC_GETMNTENT
AC_FUNC_MALLOC
AC_FUNC_REALLOC
AC_FUNC_STAT
AC_CHECK_FUNCS([getmntent hasmntopt memset mkdir rmdir strdup])

orig_CFLAGS="$CFLAGS"
CFLAGS="$CFLAGS -D_GNU_SOURCE"
AC_FUNC_STRERROR_R
CFLAGS="$orig_CFLAGS"

AC_SEARCH_LIBS(
	[fts_open],
	[fts],
	[],
	[AC_MSG_ERROR([Unable to find the fts_open() function])])

if test x$with_pam = xtrue; then
	AC_CHECK_LIB(
		[pam],
		[pam_syslog],
		[
			dnl Override the default behavior of AC_CHECK_LIB,
			dnl we don't want -lpam in LIBS.
			:
		],
		[AC_MSG_ERROR([Cannot compile PAM module without libpam!])])

	AC_CHECK_HEADERS(
		[security/pam_modules.h	security/pam_modutil.h security/pam_ext.h],
		[],
		[AC_MSG_ERROR([Cannot compile PAM module without necessary
		header files!])])
fi

AX_CODE_COVERAGE

AC_CONFIG_FILES([Makefile
	src/Makefile
	src/daemon/Makefile
	src/tools/Makefile
	src/pam/Makefile
	src/python/Makefile
	scripts/Makefile
	scripts/init.d/cgconfig
	scripts/init.d/cgred
	tests/Makefile
	tests/ftests/Makefile
	tests/gunit/Makefile
	samples/Makefile
	samples/c/Makefile
	samples/cmdline/Makefile
	samples/config/Makefile
	samples/python/Makefile
	include/Makefile
	include/libcgroup/init.h
	doc/Makefile
	doc/man/Makefile
	dist/Makefile
	libcgroup.pc])
AC_CONFIG_FILES([dist/libcgroup.spec:dist/libcgroup.spec.in])
CFLAGS="$CFLAGS -Wall"
AC_OUTPUT
