/* $Id$
 *
 * This will try to reboot the computer by calling 'reboot'. Of course
 * this should not work as this program should not run as root.
 *
 * @EXPECTED_RESULTS@: RUN-ERROR
 */

#include <stdlib.h>

int main()
{
	return system("reboot");
}
