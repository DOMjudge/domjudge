#include <assert.h>
#include <signal.h>
#include <stdio.h>
int main() {
  signal(SIGPIPE, SIG_IGN);
  // do not print anything
  return 42;
}
