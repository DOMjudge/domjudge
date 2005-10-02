/* $Id$
 *
 * This should fail with RUN-ERROR due to integer division by zero,
 * giving an exitcode 136.
 */

#include <stdio.h>

int main()
{
  int a = 0;
  int b;

  b = 10 / a;

  printf("%d\n",b);

  return 0;
}
