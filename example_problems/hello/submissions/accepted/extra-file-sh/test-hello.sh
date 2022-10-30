# This should give CORRECT on the default problem 'hello',
# since the random extra file will not be passed.
#
# @EXPECTED_RESULTS@: CORRECT

if [ -z "$DOMJUDGE" -o -z "$ONLINE_JUDGE" ]; then
	echo "Variable DOMJUDGE and/or ONLINE_JUDGE not defined."
	exit 1
fi

echo "Hello world!"

exit 0
