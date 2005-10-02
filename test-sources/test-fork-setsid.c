/* $Id$
 *
 * This will crash the judging daemon: it forks processes and places
 * these in a new session, such that test_solution cannot retrace and
 * kill these. They are left running and should be killed before
 * restarting the judging daemon.
 *
 * This is not really that bad: any team submitting this kind of code
 * should be disqualified anyways. Furthermore only this judging
 * daemon is affected and can be restarted easily. It aborts on
 * purpose to force checking of the reasons of a crash.
 */

#include <unistd.h>

int main()
{
  int parent = 1;
  int a = 0;
  
  while ( parent ) {
    if ( fork()==0 ) {
      parent = 0;
      malloc(1);
      setsid();
    }
  }

  while ( 1 ) a++;
  
  return 0;
}
