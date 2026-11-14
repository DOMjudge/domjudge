#include <unistd.h>

int main() {
  // Read only a small part of the input and exit.
  char buf[4096];
  (void)!read(STDIN_FILENO, buf, sizeof(buf));
  return 0;
}
