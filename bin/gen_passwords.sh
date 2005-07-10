#!/bin/bash
# $Id$

# Script to generate passwords for the DOMjudge system and install
# those in the relevant places. This script should be called from the
# SYSTEM_ROOT directory!

shopt -s extglob

set -e 

PROGRAM=$0

SYSTEM_ROOT=$PWD

# Location of files:
HTPASSWD="$SYSTEM_ROOT/.htpasswd"
HTACCESS="$SYSTEM_ROOT/www/jury/.htaccess"
SQLPRIVS="$SYSTEM_ROOT/sql/mysql_privileges.sql"
PHPPASSWD="$SYSTEM_ROOT/etc/passwords.php"

# Function to generate a (semi) random password. These are meant to be
# only used internally.
function generate_passwd ()
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
function ask_passwd ()
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
}

# Function to replace string(s) within a file using 'sed'. Arguments
# must be a valid 'sed' script and the file to operate on. We use
# 'cat' to copy the file's contents back to preserve file permissions.
function string_replace ()
{
    SCRIPT=$1
    FILE=$2

    [ -r "$FILE" ] || return 1

    TEMPFILE=`bin/tempfile`
    
    cat "$FILE" | sed "$SCRIPT" > "$TEMPFILE"
    cat "$TEMPFILE" > "$FILE"

    rm -f "$TEMPFILE"
}

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

# Update password info in php-config:
string_replace "s!DOMJUDGE_JURY_PASSWD!$PASSWD_JURY!g;\
                s!DOMJUDGE_TEAM_PASSWD!$PASSWD_TEAM!g;\
                s!DOMJUDGE_PUBLIC_PASSWD!$PASSWD_PUBLIC!g;" "$PHPPASSWD"

# Update password info in MySQL privileges file:
string_replace "s!DOMJUDGE_JURY_PASSWD!$PASSWD_JURY!g;\
                s!DOMJUDGE_TEAM_PASSWD!$PASSWD_TEAM!g;\
                s!DOMJUDGE_PUBLIC_PASSWD!$PASSWD_PUBLIC!g;" "$SQLPRIVS"

exit 0
