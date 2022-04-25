#include <assert.h>
#include <signal.h>
#include <stdio.h>
int main() {
  signal(SIGPIPE, SIG_IGN);
  while (1) {
    int x;
    scanf("%d", &x);
  }
}
