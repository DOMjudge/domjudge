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

for ext in .cpp .cc .c .java; do 
	if [ -r submissions/accepted ]; then
		for i in `find submissions/accepted/ -name \*$ext`; do
			base=`basename $i $ext`
			cp $i $TMPDIR/ac-${base}$ext
			echo -e "\n\n// @EXPECTED_RESULTS@: CORRECT" >> $TMPDIR/ac-${base}$ext
		done
	fi
	if [ -r submissions/wrong_answer ]; then
		for i in `find submissions/wrong_answer/ -name \*$ext`; do
			base=`basename $i $ext`
			cp $i $TMPDIR/wa-${base}$ext
			echo -e "\n\n// @EXPECTED_RESULTS@: WRONG-ANSWER" >> $TMPDIR/wa-${base}$ext
		done
	fi
	if [ -r submissions/time_limit_exceeded ]; then
		for i in `find submissions/time_limit_exceeded/ -name \*$ext`; do
			base=`basename $i $ext`
			cp $i $TMPDIR/tle-${base}$ext
			echo -e "\n\n// @EXPECTED_RESULTS@: TIMELIMIT" >> $TMPDIR/tle-${base}$ext
		done
	fi
	if [ -r submissions/run_time_error ]; then
		for i in `find submissions/run_time_error/ -name \*$ext`; do
			base=`basename $i $ext`
			cp $i $TMPDIR/rte-${base}$ext
			echo -e "\n\n// @EXPECTED_RESULTS@: RUN-ERROR" >> $TMPDIR/rte-${base}$ext
		done
	fi
done

timelimit=`cat .timelimit`
name=`cat problem_statement/problem.en.tex | grep problemname | cut -d{ -f2 | cut -d} -f1`
echo "timelimit = \"$timelimit\"" >> $TMPDIR/domjudge-problem.ini
echo "allow_submit = 1" >> $TMPDIR/domjudge-problem.ini
echo "name = \"$name\"" >> $TMPDIR/domjudge-problem.ini


zip -jr domjudge.zip $TMPDIR
echo "data stored in $TMPDIR and domjudge.zip";
