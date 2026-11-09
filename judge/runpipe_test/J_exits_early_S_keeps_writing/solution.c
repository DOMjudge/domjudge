#include <string.h>
#include <unistd.h>

int main() {
  // Keep writing, even though nobody reads it.
  static char buf[64 * 1024];
  memset(buf, '1', sizeof(buf));
  while (1) {
    (void)!write(STDOUT_FILENO, buf, sizeof(buf));
  }
}
