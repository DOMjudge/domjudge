#!/bin/bash

TMPDIR=$(mktemp -d)
SECRETPREFIX="secret_"

for i in data/sample/*in; do
	ans=`dirname $i`/`basename $i .in`.ans
	out=`basename $i .in`.out
	if [ -r `dirname $i`/`basename $i .in`.ans ]; then
		cp $i $TMPDIR/ 
		cp $ans $TMPDIR/$out
	else
		echo "Output not found for $i."
	fi
done

for i in data/secret/*in; do
	ans=`dirname $i`/`basename $i .in`.ans
	out=`basename $i .in`.out
	if [ -r `dirname $i`/`basename $i .in`.ans ]; then
		cp $i $TMPDIR/${SECRETPREFIX}`basename $i`
		cp $ans $TMPDIR/${SECRETPREFIX}$out
	else
		echo "Output not found for $i."
	fi
done

for ext in cpp cc c java; do 
	for i in submissions/accepted/*$ext; do
		base=`basename $i .$ext`
		cp $i $TMPDIR/ac-${base}.$ext
		echo -e "\n\n// @EXPECTED_RESULTS@: CORRECT" >> $TMPDIR/ac-${base}.$ext
	done
	for i in submissions/wrong_answer/*$ext; do
		base=`basename $i .$ext`
		cp $i $TMPDIR/ac-${base}.$ext
		echo -e "\n\n// @EXPECTED_RESULTS@: WRONG-ANSWER" >> $TMPDIR/wa-${base}.$ext
	done
	for i in submissions/time_limit_exceeded/*$ext; do
		base=`basename $i .$ext`
		cp $i $TMPDIR/ac-${base}.$ext
		echo -e "\n\n// @EXPECTED_RESULTS@: TIMELIMIT" >> $TMPDIR/tle-${base}.$ext
	done
	for i in submissions/run_time_error/*$ext; do
		base=`basename $i .$ext`
		cp $i $TMPDIR/ac-${base}.$ext
		echo -e "\n\n// @EXPECTED_RESULTS@: RUN-ERROR" >> $TMPDIR/rte-${base}.$ext
	done
done

echo "data stored in $TMPDIR";
