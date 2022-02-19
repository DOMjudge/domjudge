#include <assert.h>
#include <stdio.h>
#include <stdlib.h>

int main() {
  int x;
  int s = scanf("%d", &x);
  if (s != -1) {
    fprintf(stderr, "Expecting scanf to return -1, but was %d\n", s);
    abort();
  }
}
