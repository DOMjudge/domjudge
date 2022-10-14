#include <assert.h>
#include <stdio.h>

int main() {
  int a, b;
  int sum = 0;
  while (scanf("%d %d", &a, &b) == 2) {
    sum += a + b;
  }
  printf("%d\n", sum);
  return 0;
}
