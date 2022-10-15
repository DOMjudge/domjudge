#include <stdio.h>

int main() {
  fclose(stdin);
  printf("42\n");
  usleep(800000);
}
