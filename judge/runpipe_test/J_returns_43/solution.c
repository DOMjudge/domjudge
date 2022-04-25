#include <assert.h>
#include <stdio.h>

int main() {
  int x;
  assert(1 == scanf("%d", &x));
  assert(x == 123);

  assert(4 == printf("124\n"));
  fflush(stdout);
}
