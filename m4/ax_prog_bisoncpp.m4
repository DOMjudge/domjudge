# ===========================================================================
#       http://www.gnu.org/software/autoconf-archive/ax_prog_bisoncpp.html
# ===========================================================================
#
# SYNOPSIS
#
#   AX_PROG_BISONCPP([MINIMUM-VERSION], [ACTION-IF-TRUE], [ACTION-IF-FALSE])
#
# DESCRIPTION
#
#   Check whether bisonc++ is available with version at least MINIMUM-VERSION.
#   Run ACTION-IF-TRUE if successful, ACTION-IF-FALSE otherwise.
#   If bisonc++ is found, then BISONCPP_VERSION is defined as an integer
#   MAJOR*10000 + MINOR*100 + PATCHLEVEL.
#
# LICENSE
#
#   Copyright (c) 2014 Jaap Eldering <eldering@a-eskwadraat.nl>
#
#   Based on the AX_PROG_BISON macro, with copyrights:
#
#   Copyright (c) 2009 Francesco Salvestrini <salvestrini@users.sourceforge.net>
#   Copyright (c) 2010 Diego Elio Petteno` <flameeyes@gmail.com>
#
#   This program is free software; you can redistribute it and/or modify it
#   under the terms of the GNU General Public License as published by the
#   Free Software Foundation; either version 2 of the License, or (at your
#   option) any later version.
#
#   This program is distributed in the hope that it will be useful, but
#   WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
#   Public License for more details.
#
#   You should have received a copy of the GNU General Public License along
#   with this program. If not, see <http://www.gnu.org/licenses/>.
#
#   As a special exception, the respective Autoconf Macro's copyright owner
#   gives unlimited permission to copy, distribute and modify the configure
#   scripts that are the output of Autoconf when processing the Macro. You
#   need not follow the terms of the GNU General Public License when using
#   or distributing such scripts, even though portions of the text of the
#   Macro appear in them. The GNU General Public License (GPL) does govern
#   all other use of the material that constitutes the Autoconf Macro.
#
#   This special exception to the GPL applies to versions of the Autoconf
#   Macro released by the Autoconf Archive. When you make and distribute a
#   modified version of the Autoconf Macro, you may extend this special
#   exception to the GPL to apply to your modified version as well.

#serial 0

AC_DEFUN([AX_PROG_BISONCPP], [
  AC_REQUIRE([AC_PROG_EGREP])

  AC_MSG_CHECKING([for bisonc++])
  AC_CACHE_VAL([ax_cv_prog_bisoncpp],[
    AS_IF([bisonc++ --version 2>/dev/null | $EGREP -q '^bisonc\+\+ '],[
      bisoncpp_version_raw=`bisonc++ --version | sed -e 's/^bisonc++ V//'`
      bisoncpp_version=`echo $bisoncpp_version_raw | sed 's/^0*//;s/\.//g'`
      bisoncpp_version_ok=yes
      AS_IF([test -n "$1"],[
        bisoncpp_version_req=`echo "$1" | sed 's/\.//g'`
        AS_IF([test $bisoncpp_version -ge $bisoncpp_version_req],[],[bisoncpp_version_ok=no])
      ])
    ])
    ax_cv_prog_bisoncpp=no
    AS_IF([test -z "$bisoncpp_version"],AC_MSG_ERROR([not found]),
          [test "x$bisoncpp_version_ok" = "xno"],
          AC_MSG_RESULT([not found])
          AC_MSG_ERROR([bisonc++ version is $bisoncpp_version_raw[,] but >= $1 requested]),
          [ax_cv_prog_bisoncpp=yes
           AC_MSG_RESULT([found, version = $bisoncpp_version_raw])])
  ])
  AS_IF([test "x$ax_cv_prog_bisoncpp" = "xyes"],
    AC_DEFINE_UNQUOTED(BISONCPP_VERSION,[${bisoncpp_version}LL],[bisonc++ version available])
    m4_ifnblank([$2], [[$2]]),
    m4_ifnblank([$3], [[$3]])
  )
]) dnl AX_PROG_BISONCPP
