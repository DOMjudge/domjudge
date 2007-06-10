#!/bin/bash
# $Id$

# Script to generate passwords for the DOMjudge system and install
# those in the relevant places. This script should be called from the
# SYSTEM_ROOT (or SYSTEM_ROOT/bin) directory!
#
# Normally you don't want to run this script directly, but via 'make'
# instead. It gets as first argument the make target.

# Exit on any error:
set -e

shopt -s extglob

PROGRAM=$0
TARGET=$1

error()
{
	echo "$PROGRAM: $@" >&2
	exit 1
}

if [ -f etc/config.sh ]; then
	source etc/config.sh
elif [ -f ../etc/config.sh ]; then
	source ../etc/config.sh
else
	error "configuration not found: called from right dir?"
fi

# Location of files:
HTPASSWD="$SYSTEM_ROOT/.htpasswd"
HTACCESS="$SYSTEM_ROOT/www/jury/.htaccess"
SQLPASSWD="$SYSTEM_ROOT/sql/mysql_create.sql"
PHPPASSWD="$SYSTEM_ROOT/etc/passwords.php"
PASSWD_FILES="\
$PHPPASSWD
$SQLPASSWD"

# Default passwords:
DEF_PASSWD_JURY="DOMJUDGE_JURY_PASSWD"
DEF_PASSWD_TEAM="DOMJUDGE_TEAM_PASSWD"
DEF_PASSWD_PUBLIC="DOMJUDGE_PUBLIC_PASSWD"

# Function to generate a (semi) random password. These are meant to be
# only used internally.
generate_passwd ()
{
	local PASSWD=""
	local PASSWDLEN=12
	local ALPHABET="abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"
	
	for ((i=0; i<PASSWDLEN; i++)) ; do
		PASSWD="$PASSWD${ALPHABET:(($RANDOM % ${#ALPHABET})):1}"
	done

	echo "$PASSWD"

	# Clear password to reset memory (not sure how well this works...)
	PASSWD="----------------------------"
}

# Function to interactively ask a password from the user. Password
# input and output is done on stdin/stdout, messages on stderr.
ask_passwd ()
{
	local PASSWD=""
	local CONFIRM=""
	local PASSWDMINLEN=6

	while true; do
	read -e -s -p "Enter password: "   PASSWD  ; echo >&2
	read -e -s -p "Confirm password: " CONFIRM ; echo >&2

	if [ ${#PASSWD} -lt $PASSWDMINLEN ]; then
		echo "Password is too short: need at least $PASSWDMINLEN characters." >&2
		continue
	fi
	if [ "$PASSWD" != "$CONFIRM" ]; then
		echo "Password and confirmation do not match." >&2
		continue
	fi
	if [[ "$PASSWD" != *([0-9a-zA-z]) ]]; then
		echo "Password must consist of only digits and lower/uppercase letters." >&2
		continue
	fi
	break
	done

	echo "$PASSWD"

	# Clear password to reset memory (not sure how well this works...)
	PASSWD="----------------------------"
	CONFIRM="----------------------------"
}

# Function to replace string(s) within a file using 'sed'. Arguments
# must be a valid 'sed' script and the file to operate on. We use
# 'cat' to copy the file's contents back to preserve file permissions.
string_replace ()
{
	SCRIPT=$1
	FILE=$2

	[ -r "$FILE" ] || return 1

	TEMPFILE=`bin/tempfile`
	
	cat "$FILE" | sed "$SCRIPT" > "$TEMPFILE"
	cat "$TEMPFILE" > "$FILE"

	rm -f "$TEMPFILE"
}

# Function to set (clear-text) passwords in relevant files.
# Arguments: hury password, team password, public password.
set_passwords()
{
# Update password info in php-config:
string_replace "s!\('domjudge_jury'.*'pass'[ \t]*=>[ \t]*'\)[^']*!\1${1}!;\
                s!\('domjudge_team'.*'pass'[ \t]*=>[ \t]*'\)[^']*!\1${2}!;\
                s!\('domjudge_public'.*'pass'[ \t]*=>[ \t]*'\)[^']*!\1${3}!" "$PHPPASSWD"

# Update password info in MySQL file:
string_replace "s!\('domjudge_jury'.*PASSWORD('\)[^']*!\1${1}!;\
                s!\('domjudge_team'.*PASSWORD('\)[^']*!\1${2}!;\
                s!\('domjudge_public'.*PASSWORD('\)[^']*!\1${3}!" "$SQLPASSWD"
}

do_install()
{
	cat <<EOF

WARNING ABOUT PASSWORD SECURITY:

The passwords that are about to be generated/asked are stored in
plain-text in the following files:

$PASSWD_FILES

Protect these files carefully with the correct permissions and do
not choose a password equal to a sensitive existing one!

EOF

	for f in $PASSWD_FILES ; do
		if find . -perm +0077 | grep "\./$f$" >& /dev/null ; then
			echo "WARNING: '$f' found with group/other permissions!"
			echo "WARNING: Check that permissions are set correctly!"
		fi
	done

	echo "Generating 'domjudge_team' password..."
	PASSWD_TEAM=`generate_passwd`
	echo "Generating 'domjudge_public' password..."
	PASSWD_PUBLIC=`generate_passwd`

	cat <<EOF

Please enter a password for the DOMjudge jury account.
This password will be needed for getting access to the jury part of
the webinterface and to the MySQL domjudge database. The accountname
in both cases is 'domjudge_jury'.
EOF

	PASSWD_JURY=`ask_passwd`

	# Generate '.htpasswd' file for restricting access to jury
	# webinterface and update it's location in '.htaccess':
	htpasswd -b -c "$HTPASSWD" "domjudge_jury" "$PASSWD_JURY"

	string_replace "s!^AuthUserFile .*!AuthUserFile $HTPASSWD!" "$HTACCESS"
	
	set_passwords "$PASSWD_JURY" "$PASSWD_TEAM" "$PASSWD_PUBLIC"
}

case "$TARGET" in
	install)
		do_install
		;;
	clean)
		;;
	distclean)
		rm -f $HTPASSWD
		set_passwords "$DEF_PASSWD_JURY" "$DEF_PASSWD_TEAM" "$DEF_PASSWD_PUBLIC"
		;;
	*) error "unknown target: '$TARGET'." ;;
esac

exit 0
