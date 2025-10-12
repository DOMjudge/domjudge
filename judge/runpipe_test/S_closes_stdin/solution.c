#define _XOPEN_SOURCE 700

#include <stdio.h>
#include <unistd.h>
#include <time.h>

int main() {
  fclose(stdin);
  printf("42\n");

  struct timespec req = {0};
  req.tv_nsec = 800000000L; // 0.8 seconds
  nanosleep(&req, NULL);
}
