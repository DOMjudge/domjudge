#include <stdio.h>
#include <signal.h>

int main() {
  signal(SIGPIPE, SIG_IGN);

  usleep(500000);
  printf("123\n");
  fflush(stdout);
  int x;
  scanf("%d", &x);
  if (x == 42)
    return 42;
  return 43;
}
