#define _XOPEN_SOURCE 700

#include <stdio.h>
#include <signal.h>
#include <unistd.h>
#include <time.h>

int main() {
  signal(SIGPIPE, SIG_IGN);

  struct timespec req = {0};
  req.tv_nsec = 500000000L; // 0.5 seconds
  nanosleep(&req, NULL);

  printf("123\n");
  fflush(stdout);
  int x;
  if (scanf("%d", &x) != 1) {
    return 1;
  }
  if (x == 42)
    return 42;
  return 43;
}
