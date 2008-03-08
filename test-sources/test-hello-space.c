/* $Id$
 *
 * This should give CORRECT, WRONG-ANSWER or PRESENTATION-ERROR on the
 * default problem 'hello' depending on how strict white space is
 * checked for.
  */

#include <stdio.h>

int main()
{
  char hello[20] = "Hello   	 world!";
  printf("%s\n",hello);
  return 0;
}
