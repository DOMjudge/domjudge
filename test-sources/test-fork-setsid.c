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
