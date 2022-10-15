#include <stdio.h>
#include <signal.h>

int main() {
  signal(SIGPIPE, SIG_IGN);

  while (1) {
    int x;
    for (int i = 0; i < 1000000; i++)
      printf("1 ");
    printf("\n");
    fflush(stdout);
    for (int i = 0; i < 1000000; i++)
      scanf("%d", &x);
  }
}
