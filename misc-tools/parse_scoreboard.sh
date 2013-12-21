#!/bin/bash
# This script takes as input a DOMjudge scoreboard table in
# tab-separated format. The header with problem ID's should be
# included, but the footer should not. Its output, when saved to a
# file, can be used as input to the 'simulate_contest' script.

CSVSEP='	'

# We use this self-made line tokenizer: although 'read' can tokenize
# into an array, it has annoying special behaviour when the input
# delimiters are whitespace (in our case: tabs), see:
# http://stackoverflow.com/questions/4622355/read-in-bash-on-tab-delimited
#
# This function returns the data in an array 'fields' and returns
# false when EOF is read.
declare -a fields
readcsvline ()
{
	local i line nfields
	IFS='' read line || return 1
	nfields=`echo "$line" | tr -dc "$CSVSEP" | wc -c`
	((nfields++))
	unset fields
	for ((i=0; i<nfields; i++)); do
		fields[$i]=`echo "$line" | cut -f $((i+1))`
	done
}

# Read header line and copy for later use:
readcsvline
for ((i=0; i<${#fields[@]}; i++)); do header[$i]=${fields[$i]} ; done

# Check to see if 'team' and 'solved' headers can be found, note that
# 'affil' header may (not) be available..
nprobs=0
if [ ${header[1]} != 'team' -o ${header[2]} != 'score' ]; then
	echo "Error: input does not look like a DOMjudge scoreboard dump."
	exit 1
fi
probstart=4
fteam=1
nprobs=$((${#header[@]} - probstart + 1))
if [ $nprobs -lt 1 ]; then
	echo "Error: input does not look like a DOMjudge scoreboard dump."
	exit 1
fi

lineno=1
while readcsvline ; do
	((lineno++))
	if [ ${#fields[@]} -ne $((probstart + nprobs)) ]; then
		echo "Error: incorrect number of fields on line $lineno: ${fields[@]}"
	fi
	team=${fields[$fteam]}
	for ((i=probstart; i<probstart+nprobs; i++)); do
		# Check if the problem is solved:
		[ "${fields[$i]#*/}" = "${fields[$i]}" ] && continue

		prob=${header[$((i-1))]}
		nsub="${fields[$i]%%/*}"
		time="${fields[$i]##*/}"

		# Insert wrong-answer submissions (one minute before correct
		# submission) to get right amount of penalty time.
		for ((j=0; j<nsub-1; j++)); do
			echo "$team	$prob	$((time-1))	wrong-answer"
		done

		# Insert the correct submission:
		echo "$team	$prob	$time	correct"
	done
done
