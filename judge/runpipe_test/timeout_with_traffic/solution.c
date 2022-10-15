#include <stdio.h>

int main() {
  while (1) {
    int x;
    for (int i = 0; i < 1000000; i++)
      scanf("%d", &x);
    for (int i = 0; i < 1000000; i++)
      printf("1 ");
    printf("\n");
    fflush(stdout);
  }
}
