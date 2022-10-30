/*
 * Submission should fail due to illegal characters in filename.
 * Note that PHP is not able to detect that this file is submitted
 * from *nix, considers '\' as path separator and truncates the
 * filename to '#.c'. Originally, this submission would give NO-OUTPUT.
 *
 * @EXPECTED_RESULTS@: NONE-SUBMIT-FAILS
 */

#include <stdio.h>

int main()
{
	return 0;
}
