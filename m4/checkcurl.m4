# http://autoconf-archive.cryp.to/ac_check_curl.html
#
# Copyright C 2008 Akos Maroy <darkeye@tyrell.hu>
# Copyright C 2008 Jaap Eldering <eldering@a-eskwadraat.nl>
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.

AC_DEFUN([AC_CHECK_CURL], [

	pushdef([REQ_VERSION],$1)
	pushdef([ACTION_IF_FOUND],$2)
	pushdef([ACTION_IF_NOT_FOUND],$3)

	succeeded=no

	if test -z "$CURL_CONFIG"; then
		AC_PATH_PROG(CURL_CONFIG, curl-config, no)
	fi

	if test "$CURL_CONFIG" = "no" ; then
		echo "*** The curl-config script could not be found. Make sure it is"
		echo "*** in your path, and that curl is properly installed."
		echo "*** Or see http://curl.haxx.se/"
	else
		## curl-config --version returns "libcurl <version>", thus cut the number
		CURL_VERSION=`$CURL_CONFIG --version | cut -d" " -f2`
		AC_MSG_CHECKING(for curl >= $REQ_VERSION)
		VERSION_CHECK=`expr $CURL_VERSION \>\= $REQ_VERSION`
		if test "$VERSION_CHECK" = "1" ; then
			AC_MSG_RESULT(yes)
			succeeded=yes

			AC_MSG_CHECKING(CURL_CFLAGS)
			CURL_CFLAGS=`$CURL_CONFIG --cflags`
			AC_MSG_RESULT($CURL_CFLAGS)

			AC_MSG_CHECKING(CURL_LIBS)
			CURL_LIBS=`$CURL_CONFIG --libs`
			AC_MSG_RESULT($CURL_LIBS)

			AC_MSG_CHECKING(CURL_PREFIX)
			CURL_PREFIX=`$CURL_CONFIG --prefix`
			AC_MSG_RESULT($CURL_PREFIX)
		else
			CURL_CFLAGS=""
			CURL_LIBS=""
			CURL_PREFIX=""
			## If we have a custom action on failure, don't print errors, but
			## do set a variable so people can do so.
			ifelse([ACTION_IF_NOT_FOUND], ,echo "can't find curl >= $1",)
		fi

		AC_SUBST(CURL_CFLAGS)
		AC_SUBST(CURL_LIBS)
		AC_SUBST(CURL_PREFIX)
	fi

	if test $succeeded = yes; then
		ifelse([]ACTION_IF_FOUND[], , :,[]ACTION_IF_FOUND[])
	else
		ifelse([]ACTION_IF_NOT_FOUND[], ,
		       AC_MSG_ERROR([Library requirements (curl) not met.]),
		       []ACTION_IF_NOT_FOUND[])
	fi

	popdef([ACTION_IF_NOT_FOUND])
	popdef([ACTION_IF_FOUND])
	popdef([REQ_VERSION])
])
