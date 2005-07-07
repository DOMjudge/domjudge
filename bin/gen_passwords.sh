#!/bin/bash
# $Id$

# Script to generate passwords for the DOMjudge system and install
# those in the relevant places. This script should be called from the
# SYSTEM_ROOT directory!

PROGRAM=$0
SYSTEM_ROOT=$PWD

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
# input and output is done one stdin/stdout, messages on stderr.
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
	break
    done

    echo "$PASSWD"

    # Clear password to reset memory (not sure how well this works...)
    PASSWD="----------------------------"
}

PASSWD_TEAM=`generate_passwd`
PASSWD_PUBLIC=`generate_passwd`

cat <<EOF
Please enter a password for the DOMjudge jury account.
This password will be needed for getting access to the jury part of
the webinterface and to the MySQL domjudge database. The accountname
in both cases is 'domjudge_jury'.
Please be aware that this password will be stored plain-text in
'SYSTEM_ROOT/etc/passwords.php', so protect that file carefully and
do not choose your password equal to a sensitive existing one.
EOF

PASSWD_JURY=`ask_passwd`

# Generate '.htpasswd' file for restricting access to jury
# webinterface and update it's location in '.htaccess':
HTPASSWD="$SYSTEM_ROOT/www/jury/.htpasswd"
HTACCESS="$SYSTEM_ROOT/www/jury/.htaccess"

htpasswd -b -c "$HTPASSWD" "domjudge_jury" "$PASSWD_JURY"

TMPFILE=`bin/tempfile`
cat "$HTACCESS" | sed "s!^AuthUserFile .*!AuthUserFile $HTPASSWD!" > "$TMPFILE"
cat "$TMPFILE" > "$HTACCESS"
rm -f "$TMPFILE"

exit 0
