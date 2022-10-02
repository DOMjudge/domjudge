# This tries to read from some file that is group root, but not world
# readable. It should fail with WRONG-ANSWER, because we first check.
# If anything is not as expected it should generate a RUN-ERROR.
#
# @EXPECTED_RESULTS@: WRONG-ANSWER

set -e

echo "Our effective user/group(s) are:"

id

echo "Our real IDs:"

echo -n "uid=" ; id -ru
echo -n "gid=" ; id -rg

# We loop over a list of files that are group root readable, but not
# world readable. First check if the file actually exists.
for f in /etc/sudoers /var/lib/dpkg/lock /etc/root-permission-test.txt ; do
	if [ -f "$f" ]; then
		echo "Let's see if we can read '$f' ..."
		if [ -r "$f" ]; then
			head "$f"
			echo "Error: we can!"
			exit 1
		fi
		echo "Nope."
	fi
done

exit 0
