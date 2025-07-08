# This tries to read from testcase run directories.
#
# @EXPECTED_RESULTS@: WRONG-ANSWER

set -e

echo "Our effective user/group(s) are:"

id

echo "Our real IDs:"

echo -n "uid=" ; id -ru
echo -n "gid=" ; id -rg
echo

ALLOW_WRITABLE='
/dev/null
'

WRITABLE=0

LAST_TESTCASE=-1

list_rec () {
	local f="$1"
	local depth="$2"

	if   [ -b "$f" ]; then echo -n 'b'
	elif [ -c "$f" ]; then echo -n 'c'
	elif [ -d "$f" ]; then echo -n 'd'
	elif [ -h "$f" ]; then echo -n 'l'
	elif [ -p "$f" ]; then echo -n 'p'
	elif [ -S "$f" ]; then echo -n 'S'
	else echo -n '-' ; fi

	if [ -r "$f" ]; then echo -n 'r' ; else echo -n '-' ; fi
	if [ -w "$f" ]; then echo -n 'w' ; else echo -n '-' ; fi
	if [ -x "$f" ]; then echo -n 'x' ; else echo -n '-' ; fi

	echo "  $f"

	if [ -w "$f" ]; then
		for i in $ALLOW_WRITABLE ; do
			if [ "$i" = "$f" ]; then break 2 ; fi
		done
		WRITABLE=$((WRITABLE+1))
	fi

	if [ "x${f#/testcase}" != "x$f" ]; then
		num="${f#/testcase}"
	fi

	if [ -d "$f" ] && [ "$depth" -le 2 ] && \
	   [ "/proc" != "$f" ] && [ "x${f#/usr/}" = "x$f" ]; then
		[ "$f" = '/' ] && f=''
		for i in "$f/"* ; do
			[ "$i" = "$f/*" ] && continue
			list_rec "$i" $((depth+1))
		done
		echo
	else
		if [ -d "$f" ]; then
			echo "  Not recursing into this directory."
		fi
	fi
}

list_rec '/' 0

if [ "$WRITABLE" -gt 0 ]; then
	echo "Error: found $WRITABLE writable files."
	exit 1
fi

exit 0
