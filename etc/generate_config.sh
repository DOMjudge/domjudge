#!/bin/bash
# $Id$

# Script to generate specific config files for all different languages
# from one global config file 'global.cfg'.

shopt -s extglob

GLOBALCONF=global.cfg
LOCALCONF=config
LOCALTEMPLATE=config.template

CONFHEADTAG="GLOBAL CONFIG HEADER"
CONFMAINTAG="GLOBAL CONFIG MAIN"

TMPFILE=`tempfile -p 'cfg' -s '.tmp'`

TMPFILE_C=`  tempfile -p 'cfg' -s '.h'`
TMPFILE_SH=` tempfile -p 'cfg' -s '.sh'`
TMPFILE_PHP=`tempfile -p 'cfg' -s '.php'`

COMMANDLINE="$0 $@"

exec 3<$GLOBALCONF

OLDIFS=$IFS

declare VARDEF VARATTR VARNAME VALUE
declare ATTR ATTR_STRING ATTR_EVAL

LINENR=0
while IFS='='; read VARDEF VALUE <&3; do
	IFS=$OLDIFS
	((LINENR++))
	
	# Ignore comments and whitespace only lines
	if [[ "$VARDEF" == *([:space:]) || "$VARDEF" == '#'* ]]; then
		continue
	fi

	ATTR_STRING=0
	ATTR_EVAL=0
	# Check for attributes
	if [[ "$VARDEF" == *'['* ]]; then
		if [[ "$VARDEF" != [A-Za-z]*([A-Za-z0-9_])'['+([a-z])*(,+([a-z]))']' ]]; then
			echo "Parse error on line $LINENR!"
			exit 1
		fi
		VARNAME=${VARDEF%%'['*}
		VARATTR=${VARDEF##*'['}
		VARATTR=${VARATTR%']'}
		IFS="$IFS,"
		for ATTR in $VARATTR; do
			[[ "$ATTR" == "string" ]] && ATTR_STRING=1
			[[ "$ATTR" == "eval"   ]] && ATTR_EVAL=1
		done
		IFS=$OLDIFS
	else
		if [[ "$VARDEF" != [A-Za-z]*([A-Za-z0-9_]) ]]; then
			echo "Parse error on line $LINENR!"
			exit 1
		fi
		VARNAME=$VARDEF
	fi

	if [ $ATTR_EVAL -ne 0 ]; then
		eval VALUE="$VALUE"
	fi

	if [ $ATTR_STRING -ne 0 ]; then
		echo "#define $VARNAME \"$VALUE\""   >>$TMPFILE_C
		echo "$VARNAME=\"$VALUE\""           >>$TMPFILE_SH
		echo "define('$VARNAME', '$VALUE');" >>$TMPFILE_PHP
	else
		echo "#define $VARNAME $VALUE"     >>$TMPFILE_C
		echo "$VARNAME=$VALUE"             >>$TMPFILE_SH
		echo "define('$VARNAME', $VALUE);" >>$TMPFILE_PHP
	fi

	if set | grep ^${VARNAME}= &>/dev/null; then
		echo "Variable '$VARNAME' already in use on line $LINENR!"
		exit 1
	fi

	eval $VARNAME="'$VALUE'"
done

exec 3<&-

function config_include ()
{
	local FROMFILE=$1
	local TOFILE=$2
	local COMMENT=$3

	local NSTART NEND

	NSTART=`grep "$CONFHEADTAG START" $TOFILE | wc -l`
	NEND=`grep "$CONFHEADTAG END" $TOFILE | wc -l`
	if [ $NSTART -gt 1 -o $NEND -gt 1 -o $NSTART -ne $NEND ]; then
		echo "Incorrect header START and/or END tags in $TOFILE!"
		exit 1
	fi

	grep -B 1000 "$CONFHEADTAG START" $TOFILE >$TMPFILE
	cat >>$TMPFILE <<EOF
$COMMENT
$COMMENT This configuration file was automatically generated
$COMMENT on `date` on host '$HOSTNAME'.
$COMMENT
$COMMENT Only edit parts of this file by hand, which are outside the
$COMMENT '$CONFHEADTAG' and '$CONFMAINTAG' tags.
$COMMENT
$COMMENT Configuration options inside '$CONFMAINTAG' tags
$COMMENT should be edited in the main configuration file '$GLOBALCONF'
$COMMENT and then be included here by running the '`basename $0`'
$COMMENT command.
$COMMENT
EOF
	grep -A 1000 "$CONFHEADTAG END" $TOFILE >>$TMPFILE

	NSTART=`grep "$CONFMAINTAG START" $TOFILE | wc -l`
	NEND=`grep "$CONFMAINTAG END" $TOFILE | wc -l`
	if [ $NSTART -gt 1 -o $NEND -gt 1 -o $NSTART -ne $NEND ]; then
		echo "Incorrect main config START and/or END tags in $TOFILE!"
		exit 1
	fi

	grep -B 1000 "$CONFMAINTAG START" $TMPFILE >$TOFILE
	cat $FROMFILE >>$TOFILE
	grep -A 1000 "$CONFMAINTAG END" $TMPFILE >>$TOFILE
}

cp -a $LOCALTEMPLATE.h   $LOCALCONF.h
cp -a $LOCALTEMPLATE.sh  $LOCALCONF.sh
cp -a $LOCALTEMPLATE.php $LOCALCONF.php

config_include $TMPFILE_C   ${LOCALCONF}.h   '//'
config_include $TMPFILE_SH  ${LOCALCONF}.sh  '#'
config_include $TMPFILE_PHP ${LOCALCONF}.php '//'

rm -f $TMPFILE $TMPFILE_C $TMPFILE_SH $TMPFILE_PHP

exit 0
