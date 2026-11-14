#include <signal.h>
#include <stdio.h>
#include <string.h>
#include <unistd.h>

#define TOTAL (16 * 1024 * 1024)

int main() {
  signal(SIGPIPE, SIG_IGN);

  // Write much more than fits in the pipes. Pause after the first chunk so
  // that the solution has exited before we write the rest.
  static char buf[64 * 1024];
  memset(buf, '1', sizeof(buf));
  for (size_t written = 0; written < TOTAL; written += sizeof(buf)) {
    if (write(STDOUT_FILENO, buf, sizeof(buf)) != sizeof(buf)) {
      break;
    }
    if (written == 0) {
      usleep(250 * 1000);
    }
  }

  // Wait for the solution to close its stdout.
  while (read(STDIN_FILENO, buf, sizeof(buf)) > 0) {
  }
  return 42;
}
