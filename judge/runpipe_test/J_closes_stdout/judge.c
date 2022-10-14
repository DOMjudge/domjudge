#include <assert.h>
#include <signal.h>
#include <stdio.h>

int x = 0;

int main() {
  signal(SIGPIPE, SIG_IGN);
  printf("1 2\n");
  printf("3 4\n");
  fclose(stdout);
  int sum;
  scanf("%d", &sum);
  if (sum == 1 + 2 + 3 + 4)
    return 42;
  return 43;
}
