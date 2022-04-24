#include <assert.h>
#include <fcntl.h>
#include <signal.h>
#include <sys/stat.h>
#include <unistd.h>

void sigterm() {
  assert(open("judge.txt", O_WRONLY | O_CREAT, S_IRUSR | S_IWUSR) != -1);
  _exit(42);
}

int main() {
  signal(SIGPIPE, SIG_IGN);
  signal(SIGTERM, sigterm);

  for (int i = 0; i < 10000; i++)
    sleep(1000);
}
