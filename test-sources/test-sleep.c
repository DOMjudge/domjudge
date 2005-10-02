/* $Id$
 *
 * This should fail with a TIMELIMIT.
 */

#include <unistd.h>

int main()
{
  while ( 1 ) sleep(1);
  
  return 0;
}
