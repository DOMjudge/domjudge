#include <assert.h>
#include <signal.h>
#include <stdio.h>
int main() {
  signal(SIGPIPE, SIG_IGN);
  assert(4 == printf("123\n"));
  fflush(stdout);

  int x;
  assert(1 == scanf("%d", &x));
  if (x == 123)
    return 42;
  return 43;
}
