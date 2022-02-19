#include <assert.h>
#include <signal.h>
#include <stdio.h>
int main() {
  signal(SIGPIPE, SIG_IGN);
  assert(4 == printf("123\n"));
  fflush(stdout);

  int x;
  int s = scanf("%d", &x);
  if (s != -1) {
    fprintf(stderr, "Expecting scanf to return -1, but was %d\n", s);
    return 44;
  }
  return 42;
}
