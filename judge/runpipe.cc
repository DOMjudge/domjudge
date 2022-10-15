/*
  runpipe -- run two commands with stdin/stdout bi-directionally connected.

  Idea based on the program dpipe from the Virtual Distributed
  Ethernet package.

  Part of the DOMjudge Programming Contest Jury System and licensed
  under the GNU GPL. See README and COPYING for details.


  Program specifications:

  This program will run two specified commands and connect their
  stdin/stdout to each other.

  When this program is sent a SIGTERM, this signal is passed to both
  programs. This program will return when both programs are finished
  and reports back the exit code of the first program.
*/

// Architecture
// ------------
//
// Two processes have to be spawned and their stdio redirected: stdout of
// one to stdin of the other. Furthermore, we must be able to look at their
// traffic and write it to file.
//
// We call "proxy" the component that inspects the traffic and writes it to
// the output file. The proxy is enabled only if the output file is provided.
//
// With #0 we refer to the first process (the main process), with #1 to the
// second process.
//
// To do so we use epoll (requires Linux ~2.6) to wait for:
// (a) communication between the processes
// (b) exit of a process
//
// Unfortunately, epoll doesn't support waiting for a process exit, but we can
// emulate it by sending a message in the SIGCHLD signal handler using an extra
// pipe.
//
// If the proxy is not enabled (i.e. no traffic capturing), the pipes are setup
// like this:
//
//   #0            #1
// stdout -----> stdin
// stdin  <----- stdout
// SIGCHLD -----------------> epoll
//
//
// If the proxy is enabled the pipes are setup like this:
//
//   #0            proxy           #1
// stdout  ----->  epoll  -----> stdin
// stdin   <-----  epoll  <----- stdout
// SIGCHLD ----------^

#include "config.h"

#include "lib.error.h"
#include "lib.misc.h"

#include <chrono>
#include <fcntl.h>
#include <fstream>
#include <getopt.h>
#include <signal.h>
#include <sstream>
#include <string>
#include <sys/epoll.h>
#include <sys/wait.h>
#include <tuple>
#include <unistd.h>
#include <vector>

#define PROGRAM "runpipe"
#define VERSION DOMJUDGE_VERSION "/" REVISION

using namespace std;

using fd_t = int;

const char *progname;
const int N_PROC = 2;

// Set the NONBLOCK flag for a file descriptor.
void set_non_blocking(fd_t fd) {
  int flags = fcntl(fd, F_GETFL, 0);
  if (fcntl(fd, F_SETFL, flags | O_NONBLOCK)) {
    error(errno, "failed to set fd %d to non blocking", fd);
  }
}

/* Try to resize pipes to their maximum size on Linux. We do this to make it
   as unlikely as possible for either the jury or team program to get blocked
   writing to the other side, if that side doesn't consume data from the pipe.
   See also: https://github.com/Kattis/problemtools/issues/113
 */
// For Linux specific fcntl F_SETPIPE_SZ command.
#if __gnu_linux__
const char *PROC_MAX_PIPE_SIZE = "/proc/sys/fs/pipe-max-size";

void resize_pipe(int fd) {
  const int UNINIT = -1;
  const int FAILED = -2;
  static int max_pipe_size = UNINIT;

  if (max_pipe_size == FAILED) {
    return;
  }
  if (max_pipe_size == UNINIT) {
    FILE *f = nullptr;
    if ((f = fopen(PROC_MAX_PIPE_SIZE, "r")) == NULL) {
      max_pipe_size = FAILED;
      warning(errno, "could not open '%s'", PROC_MAX_PIPE_SIZE);
      return;
    }
    if (fscanf(f, "%d", &max_pipe_size) != 1) {
      max_pipe_size = FAILED;
      warning(errno, "could not read from '%s'", PROC_MAX_PIPE_SIZE);
      if (fclose(f) != 0) {
        warning(errno, "could not close '%s'", PROC_MAX_PIPE_SIZE);
      }
      return;
    }
    if (fclose(f) != 0) {
      warning(errno, "could not close '%s'", PROC_MAX_PIPE_SIZE);
    }
  }

  int new_size = fcntl(fd, F_SETPIPE_SZ, max_pipe_size);
  if (new_size == -1) {
    warning(errno, "could not change pipe size of %d", fd);
  }

  logmsg(LOG_DEBUG, "set pipe fd %d to size %d", fd, new_size);
}
#else  // __gnu_linux__
void resize_pipe(int fd) {}
#endif // __gnu_linux__

// Write all the data into the file descriptor. It is assumed that the file
// descriptor is *not* NONBLOCK. This function blocks until all the data is
// written.
void write_all(fd_t fd, const char *data, ssize_t size) {
  ssize_t index = 0;
  while (index < size) {
    ssize_t nwrite = write(fd, data + index, size - index);
    // Note that this pipe is not NONBLOCK, so here we may block (but
    // usually don't).
    if (nwrite < 0) {
      // This may fail, for example if the solution closes stdin.
      break;
    }
    index += nwrite;
  }
}

// This struct contains all the metadata and runtime information of a process to
// spawn.
struct process_t {
  // The 0-based index of this process. The 0-th process is the main process.
  size_t index;

  fd_t stdout = -1; // FD of where the stdout is redirected to.
  fd_t stdin = -1;  // FD of where the stdin is coming from.

  // If the proxy is active (i.e. -o is provided), these are the file
  // descriptors for its communication.
  fd_t proxy_to_process = -1;
  fd_t process_to_proxy = -1;

  // The command to execute and its arguments.
  string cmd;
  vector<string> args;

  // When the process has been spawned, this contains the pid of the child
  // process.
  pid_t pid = -1;

  bool exited = false;
  // Information about the exited process. Meaningful only if exited == true.
  int exitInfo = -1;

  process_t(size_t index) : index(index) {}

  string debug() const {
    stringstream s;
    s << cmd;
    for (const auto &arg : args) {
      s << ' ' << arg;
    }
    return s.str();
  }

  string exit_info_to_string() const {
    if (!exited) {
      return "not exited yed";
    }
    if (WIFEXITED(exitInfo)) {
      return string("exited with status ") + to_string(WEXITSTATUS(exitInfo));
    }
    if (WIFSIGNALED(exitInfo)) {
      return string("exited with signal ") + to_string(WTERMSIG(exitInfo));
    }
    return "unknown";
  }

  // Whether this process exited with a status code (and not with a signal).
  bool has_exited_with_code() const {
    if (!exited) {
      return false;
    }
    return WIFEXITED(exitInfo);
  }

  // Whether this process exited with a signal (and not with a status code).
  bool has_exited_with_signal() const {
    if (!exited) {
      return false;
    }
    return WIFSIGNALED(exitInfo);
  }

  int exit_code() const {
    if (!has_exited_with_code()) {
      return -1;
    }
    return WEXITSTATUS(exitInfo);
  }

  // Fork and exec the child process, redirecting its standard I/O.
  void spawn() {
    fd_t stdio[3] = {stdin, stdout, FDREDIR_NONE};

    vector<const char *> argv(args.size());
    for (size_t i = 0; i < args.size(); i++) {
      argv[i] = args[i].c_str();
    }
    pid = execute(cmd.c_str(), argv.data(), args.size(), stdio, 0);
    if (pid < 0) {
      error(errno, "failed to execute command #%ld", index);
    }
    logmsg(LOG_DEBUG, "started #%ld, pid %d", index, pid);
    // Do not leak these file descriptors, otherwise we cannot detect if the
    // process has closed stdout.
    close(stdin);
    close(stdout);
  }

  // Function called when the process exits.
  void on_exit(int status) {
    exited = true;
    exitInfo = status;
  }

  // Close the file descriptor of the pipe coming into the process
  // (i.e. proxy -> process).
  void close_input_fd() {
    if (proxy_to_process != -1) {
      logmsg(LOG_DEBUG, "closing fd: %d (proxy -> process) of %d",
             proxy_to_process, pid);
      close(proxy_to_process);
    }
  }

  // Close the file descriptor of the pipe going out from the process
  // (i.e. process -> proxy).
  void close_output_fd() {
    if (process_to_proxy != -1) {
      logmsg(LOG_DEBUG, "closing fd: %d (process -> proxy) of %d",
             process_to_proxy, pid);
      close(process_to_proxy);
    }
  }

  // Close the pipes we keep alive feeding data to the process. By closing
  // these we signal the child that no more data is coming.
  void close_fds() {
    close_input_fd();
    // If this process is the one that exited, we should also close the pipes
    // coming out of there. This will make sure all the pipes are closed.
    if (exited) {
      close_output_fd();
    }
  }
};

// Wrapper for writing data to the output file. This writes the communication
// between the processes using the following format:
//
// [time_in_seconds/bytes]direction: content\n
//
// Where:
//   time_in_seconds: the amount of time passed from the start of the execution
//   bytes: the number of bytes of "content"
//   direction: > if "content" is sent by the main process, < otherwise
//   content: a sequence of "bytes" bytes, followed by a new-line
struct output_file_t {
  // The file descriptor of the file where to write.
  fd_t output_file = -1;

  chrono::time_point<chrono::steady_clock> start;

  output_file_t(string path) {
    // If the output file is not enable this struct only does noops.
    if (path.empty()) {
      return;
    }

    start = chrono::steady_clock::now();
    output_file = open(path.c_str(), O_CREAT | O_CLOEXEC | O_WRONLY | O_TRUNC,
                       S_IRUSR | S_IWUSR | S_IRGRP | S_IWGRP);
    if (output_file == -1) {
      error(errno, "failed to create proxy output file at %s", path.c_str());
    }
  }

  output_file_t(const output_file_t &) = delete;
  output_file_t(const output_file_t &&) = delete;
  output_file_t &operator=(const output_file_t &) = delete;
  output_file_t &operator=(const output_file_t &&) = delete;

  ~output_file_t() {
    if (output_file == -1) {
      return;
    }
    if (close(output_file)) {
      error(errno, "failed to close proxy output file");
    }
  }

  // Write all the data into the output file, including the header of this
  // message. The buffer should be at least long size+1.
  void write(char *buffer, ssize_t size, const process_t &from) {
    if (output_file == -1) {
      return;
    }

    // The runtime is converted into sec + millis manually instead of with %f
    // because benchmarks showed that it's quite expensive.
    auto duration = chrono::steady_clock::now() - start;
    auto time = duration.count() / 1000 / 1000; // ns -> ms
    int time_sec = time / 1000;
    int time_millis = time % 1000;

    const size_t HEADER_SIZE = 64;
    char header[HEADER_SIZE];

    char direction = from.index == 0 ? '>' : '<';
    int header_len =
        snprintf(header, HEADER_SIZE, "[%3d.%03ds/%ld]%c: ", time_sec,
                 time_millis, size, direction);
    // Check that snprintf didn't truncate the header.
    if (header_len >= static_cast<int>(HEADER_SIZE)) {
      error(0, "header size too small: %d > %ld", header_len, HEADER_SIZE);
    }

    write_all(output_file, header, header_len);
    buffer[size] = '\n'; // avoids another call to write_all just for the \n
    write_all(output_file, buffer, size + 1);
  }
};

void usage() {
  printf("\
Usage: %s [OPTION]... COMMAND1 [ARGS...] = COMMAND2 [ARGS...]\n\
Run two commands with stdin/stdout bi-directionally connected.\n\
\n",
         progname);
  printf("\
  -o, --outprog=FILE   write stdout from second program to FILE\n\
  -M, --outmeta=FILE   write metadata (runtime, exit_code, etc.) of first program to FILE\n\
  -v, --verbose        display some extra warnings and information\n\
  -h, --help           display this help and exit\n\
      --version        output version information and exit\n\
\n\
Arguments starting with a `=' must be escaped by prepending an extra `='.\n");
  exit(0);
}

// This struct contains most of the runtime information of this tool, including
// the command line arguments.
struct state_t {
  // Parsed command line arguments.
  struct {
    bool verbose = false;
    int show_help = 0;
    int show_version = 0;
    string output_file;
    string meta_file;
  } args;

  // The N_PROC processes to execute.
  vector<process_t> processes;

  // The PID of the first process that exited.
  pid_t first_process_exit_id = -1;

  // The pipe from which the events about the child exits can be read. The
  // events are writted by the SIGCHLD handler.
  fd_t child_exited_pipe = -1;
  // The file descriptor of the epoll.
  fd_t epoll_fd = -1;

  // The instant of when the whole process started. It's used to write the total
  // runtime in the metadata file.
  chrono::time_point<chrono::high_resolution_clock> start =
      chrono::high_resolution_clock::now();
  // The total amount of bytes that are transferred between the processes. It's
  // filled only if the proxy is active.
  size_t total_bytes_transferred = 0;

  state_t(int argc, char **argv) {
    parse_flags(argc, argv);
    parse_commands(argc, argv);
  }

  void parse_flags(int argc, char **argv) {
    // clang-format off
    struct option const long_opts[] = {
      {"verbose", no_argument,       nullptr,            'v'},
      {"help",    no_argument,       &args.show_help,    1  },
      {"version", no_argument,       &args.show_version, 1  },
      {"outprog", required_argument, nullptr,            'o'},
      {"outmeta", required_argument, nullptr,            'M'},
      { nullptr,  0,                 nullptr,             0 }
    };
    // clang-format on

    progname = argv[0];
    int opt = -1;
    while ((opt = getopt_long(argc, argv, "+o:M:vh", long_opts, NULL)) != -1) {
      switch (opt) {
      case 0: /* long-only option */
        break;
      case 'v': /* verbose option */
        args.verbose = true;
        verbose = LOG_DEBUG;
        logmsg(LOG_DEBUG, "verbose mode enabled");
        break;
      case 'o': /* outprog option */
        args.output_file = optarg;
        logmsg(LOG_DEBUG, "writing interactions to '%s'",
               args.output_file.c_str());
        break;
      case 'M': /* outmeta option */
        args.meta_file = optarg;
        logmsg(LOG_DEBUG, "writing metadata to '%s'", args.meta_file.c_str());
        break;
      case 'h':
        args.show_help = 1;
        break;
      case ':': /* getopt error */
      case '?':
        error(0, "unknown option or missing argument `%c'", optopt);
        break;
      default:
        error(0, "getopt returned character code `%c' ??", (char)opt);
      }
    }

    if (args.show_help) {
      usage();
    }
    if (args.show_version) {
      version(PROGRAM, VERSION);
    }

    if (argc <= optind) {
      logmsg(LOG_ERR, "no command specified");
      exit(1);
    }
  }

  // Parse the N_PROC commands separated by '='.
  void parse_commands(int argc, char **argv) {
    for (size_t i = 0; i < N_PROC; i++) {
      process_t proc(i);
      processes.emplace_back(move(proc));
    }

    size_t current_process_index = 0;
    for (int i = optind; i < argc; i++) {
      string arg = argv[i];

      // Command separator.
      if (arg == "=") {
        current_process_index += 1;
        if (current_process_index >= N_PROC) {
          logmsg(LOG_ERR, "too many commands specified!");
          exit(1);
        }
        continue;
      }

      // Unescape arguments: "==" -> "=".
      if (arg.substr(0, 2) == "==") {
        arg = arg.substr(1);
      }

      process_t &process = processes[current_process_index];

      // The first argument is the command.
      if (process.cmd.empty()) {
        process.cmd = arg;
        continue;
      }

      // The rest of the arguments are the arguments of the command
      process.args.emplace_back(move(arg));
    }

    if (processes.back().cmd.empty()) {
      logmsg(LOG_ERR, "you should provide %d commands", N_PROC);
      exit(1);
    }

    if (args.verbose) {
      logmsg(LOG_DEBUG, "Processes:");
      for (size_t i = 0; i < processes.size(); i++) {
        logmsg(LOG_DEBUG, "  #%ld: %s", i, processes[i].debug().c_str());
      }
    }
  }

  process_t &main_process() { return processes.front(); }

  bool has_proxy() { return !args.output_file.empty(); }

  // Install an handler for the SIGTERM signal. This will send SIGTERM to all
  // the children and then restore the default signal handler.
  void install_sigterm_handler() {
    sigset_t sigmask;
    struct sigaction sigact {};

    if (sigemptyset(&sigmask)) {
      error(errno, "creating signal mask");
    }
    if (sigprocmask(SIG_SETMASK, &sigmask, NULL)) {
      error(errno, "unmasking signals");
    }
    if (sigaddset(&sigmask, SIGTERM)) {
      error(errno, "setting signal mask");
    }

    sigact.sa_flags = SA_RESETHAND | SA_RESTART;
    sigact.sa_mask = sigmask;
    sigact.sa_handler = [](int) {
      // When SIGTERM is received, the original handler is restored and then
      // the signal is propagated to the children.
      struct sigaction sigact {};
      sigact.sa_handler = SIG_IGN;
      sigact.sa_flags = 0;
      if (sigemptyset(&sigact.sa_mask)) {
        warning(errno, "creating signal mask");
      }
      if (sigaction(SIGTERM, &sigact, NULL)) {
        warning(errno, "cannot restore signal handler");
      }

      logmsg(LOG_DEBUG, "sending SIGTERM to child processes");
      if (kill(0, SIGTERM)) {
        error(errno, "sending SIGTERM");
      }
    };

    logmsg(LOG_DEBUG, "installing SIGTERM handler");
    if (sigaction(SIGTERM, &sigact, NULL)) {
      error(errno, "installing signal handler");
    }
  }

  // Install an handler for the SIGCHLD signal. The handler will send a byte to
  // a pipe notifying the main loop that a child exited.
  // This method can be called only once.
  void install_sigchld_handler() {
    fd_t fds[2];
    if (pipe2(fds, O_CLOEXEC | O_NONBLOCK)) {
      error(errno, "creating exit pipes");
    }

    // The lambda below cannot capture anything, otherwise it couldn't be made
    // into a function pointer. Therefore the write_end must have a static
    // lifetime.
    fd_t read_end = fds[0];
    static fd_t write_end = -1;
    if (write_end != -1) {
      error(0, "install_sigchld_handler can be called only once");
    }
    write_end = fds[1];

    logmsg(LOG_DEBUG, "exit handler will send event using %d -> %d", write_end,
           read_end);

    signal(SIGCHLD, [](int) {
      // TODO: Decide whether to keep some logging as the line below. We can't
      // use logmsg here since that will in turn call syslog which is not safe
      // to do in a signal handler (see also `man signl-safety`).
      // logmsg(LOG_DEBUG, "caught SIGCHLD signal");

      // Notify the main loop that a child exited by sending a message via
      // child_exited_pipe.
      static char buf[] = {42};
      if (write(write_end, buf, 1) != 1) {
        error(errno, "failed to notify child exit");
      }
    });

    child_exited_pipe = read_end;
  }

  // Create the pipes used for the process communication, including the ones for
  // the proxy, if enabled.
  void setup_pipes() {
    // Create and setup a pipe.
    auto make_pipe = [&]() {
      fd_t fds[2];
      if (pipe2(fds, O_CLOEXEC)) {
        error(errno, "creating pipes");
      }
      fd_t read_end = fds[0];
      fd_t write_end = fds[1];
      resize_pipe(read_end);
      resize_pipe(write_end);

      return make_pair(read_end, write_end);
    };

    for (size_t i = 0; i < processes.size(); i++) {
      size_t j = (i + 1) % N_PROC;
      // Setup the communication #i -> #j (optionally with an proxy in
      // between).
      process_t &process = processes[i];
      process_t &other = processes[j];

      fd_t read_end = -1, write_end = -1;
      if (has_proxy()) {
        // Use two pipes for the given direction with the
        // proxy in between.
        tie(read_end, write_end) = make_pipe();
        logmsg(LOG_DEBUG, "setting up pipe #%ld (fd %d) -> proxy (fd %d)", i,
               write_end, read_end);
        process.stdout = write_end;
        process.process_to_proxy = read_end;
        set_non_blocking(process.process_to_proxy);

        tie(read_end, write_end) = make_pipe();
        logmsg(LOG_DEBUG, "setting up pipe proxy (fd %d) -> #%ld (fd %d)",
               write_end, j, read_end);
        other.proxy_to_process = write_end;
        other.stdin = read_end;
      } else {
        // No proxy: direct communication.
        tie(read_end, write_end) = make_pipe();
        logmsg(LOG_DEBUG, "setting up pipe #%ld (fd %d) -> #%ld (fd %d)", i,
               write_end, j, read_end);
        process.stdout = write_end;
        other.stdin = read_end;
      }
    }
  }

  // Create the epoll and register the file descriptors to it.
  void init_epoll() {
    epoll_fd = epoll_create1(0);
    if (epoll_fd == -1) {
      error(errno, "error creating epoll");
    }

    auto add_fd = [&](fd_t fd) {
      logmsg(LOG_DEBUG, "epoll will listen for fd %d", fd);
      epoll_event ev{};
      ev.data.fd = fd;
      ev.events = EPOLLIN;
      if (epoll_ctl(epoll_fd, EPOLL_CTL_ADD, fd, &ev)) {
        error(errno, "failed to add fd %d to epoll", fd);
      }
    };

    // Always listen for child exit events.
    if (child_exited_pipe == -1) {
      error(0, "SIGCHLD handler not installed");
    }
    add_fd(child_exited_pipe);

    // Listen for incoming data only when proxy is enabled.
    if (has_proxy()) {
      for (auto &proc : processes) {
        add_fd(proc.process_to_proxy);
      }
    }
  }

  // Wait for an exited child without blocking. If a child has been waited
  // successfully this method returns true.
  bool handle_child_exit() {
    // Consume what the signal handler wrote in this pipe.
    static char buffer[1];
    if (read(child_exited_pipe, buffer, 1) != 1) {
      // This function may be called also if no one wrote in the pipe, so ignore
      // those errors but still try to wait for a child.
      if (errno != EAGAIN && errno != EWOULDBLOCK) {
        error(errno, "failed to read from exit pipe");
      }
    }

    int status = -1;
    // Check if a child exited without blocking.
    pid_t pid = waitpid(-1, &status, WNOHANG);
    if (pid < 0) {
      error(errno, "failed to wait for child exit");
    }
    // No child has exited.
    if (pid == 0) {
      return false;
    }

    logmsg(LOG_DEBUG, "child with pid %d exited", pid);

    if (first_process_exit_id == -1) {
      first_process_exit_id = pid;
    }

    // Search the exited process and store its exit information.
    bool found = false;
    for (auto &proc : processes) {
      if (proc.pid != pid) {
        continue;
      }

      proc.on_exit(status);
      found = true;
      break;
    }

    // One of the process exited, close all the fd. `close_fds` must be called
    // after `on_exit`.
    for (auto &proc : processes) {
      proc.close_fds();
    }

    if (!found) {
      error(0, "unknown child with pid %d exited", pid);
    }

    return true;
  }

  // Check if every process has exited.
  bool has_everyone_exited() {
    for (const auto &proc : processes) {
      if (!proc.exited) {
        return false;
      }
    }
    return true;
  }

  // The pipe connecting from -> to has some data ready. Consume it reading as
  // much as possible, copy it to the target process and write it to the output
  // file.
  void pump_proxy_pipe(process_t &from, process_t &to,
                       output_file_t &output_file) {
    const size_t BUF_SIZE = 1024 * 1024;
    char buffer[BUF_SIZE];
    while (true) {
      // Read from the process to the proxy until EOF or the read would
      // block. Do not fill the buffer completely since output_file_t needs to
      // write an extra \n at its end.
      ssize_t nread = read(from.process_to_proxy, buffer, BUF_SIZE - 1);
      if (nread == 0) {
        warning(0, "EOF from process #%ld", from.index);
        // The process closed stdout, we need to close the pipe's file
        // descriptors as well.
        to.close_input_fd();
        from.close_output_fd();
        return;
      }
      if (nread < 0) {
        // We read what was ready, don't block and return.
        if (errno == EAGAIN || errno == EWOULDBLOCK) {
          return;
        }
        error(errno, "failed to read from pipe of #%ld", from.index);
      }
      // We've read nread bytes, write them to the other process' pipe.
      write_all(to.proxy_to_process, buffer, nread);
      // Write them also to the output file.
      output_file.write(buffer, nread, from);

      total_bytes_transferred += nread;
    }
    error(0, "unexpected exit from pump loop");
  };

  // Start listening for file events and block until all the processes exit.
  void epoll_loop() {
    output_file_t output_file(args.output_file);

    // We can only receive 2 types of events:
    // - a child exited
    // - some data is ready in an proxy's pipe (at most N_PROC)
    const int MAX_EVENTS = 1 + N_PROC;
    epoll_event events[MAX_EVENTS];
    while (true) {
      // This will block until an event is ready.
      int num_events = epoll_wait(epoll_fd, events, MAX_EVENTS, -1);
      if (num_events == -1) {
        // When a signal is triggered, epoll_wait exits with EINTR, but that's
        // ok for us. We can just wait again.
        if (errno == EINTR) {
          continue;
        }
        error(errno, "failed to wait on epoll");
      }

      for (int i = 0; i < num_events; i++) {
        auto &event = events[i];
        fd_t fd = event.data.fd;

        // The exit signal handler noticed a child's exit.
        if (fd == child_exited_pipe) {
          // Consume all the exited children.
          while (handle_child_exit()) {
            if (has_everyone_exited()) {
              goto finish;
            }
          }
          // If the main process crashed (with a signal) something bad is
          // happening and the communication with the other process may be very
          // broken.
          if (main_process().has_exited_with_signal()) {
            logmsg(LOG_WARNING, "the first process crashed! %s",
                   processes[0].exit_info_to_string().c_str());
          }
          continue;
        }

        // A process wrote in one of the pipes to the proxy.
        for (size_t i = 0; i < processes.size(); i++) {
          auto &from = processes[i];
          if (fd != from.process_to_proxy) {
            continue;
          }
          auto &to = processes[(i + 1) % processes.size()];
          // Do not write to an exited process.
          if (to.exited) {
            break;
          }
          pump_proxy_pipe(from, to, output_file);
        }
      }
    }

  finish:
    logmsg(LOG_DEBUG, "all processes exited");
    if (!args.output_file.empty()) {
      logmsg(LOG_INFO, "total communication amount: %ld KiB",
             total_bytes_transferred / 1024);
    }
  }

  // Write the metadata to file, if enabled.
  void write_meta() {
    if (args.meta_file.empty()) {
      return;
    }

    auto total_duration = chrono::high_resolution_clock::now() - start;

    ofstream meta(args.meta_file);
    if (meta.fail()) {
      error(errno, "failed to open meta file at %s", args.meta_file.c_str());
    }
    meta << "exitcode: " << main_process().exit_code() << endl;
    meta << "bytes-transferred: " << total_bytes_transferred << endl;
    meta << "total-duration-us: " << total_duration.count() / 1000 << endl;
    meta << "validator-exited-first: "
         << (first_process_exit_id == main_process().pid ? "true" : "false")
         << endl;
  }
};

int main(int argc, char **argv) {
  state_t state(argc, argv);

  // Enter a new session since we are dealing with signals.
  if (setsid() < 0) {
    error(errno, "failed to create a new session");
  }
  // The processes may close their pipes, so we need to ignore "broken pipe"
  // errors.
  signal(SIGPIPE, SIG_IGN);
  state.install_sigterm_handler();
  state.install_sigchld_handler();
  state.setup_pipes();
  for (auto &proc : state.processes) {
    proc.spawn();
  }

  state.init_epoll();
  state.epoll_loop();
  state.write_meta();

  if (state.args.verbose) {
    logmsg(LOG_DEBUG, "Exit statuses:");
    for (const auto &proc : state.processes) {
      logmsg(LOG_DEBUG, "  #%ld: %s", proc.index,
             proc.exit_info_to_string().c_str());
    }
  }

  // The exit status should match the one of the first command.
  auto main_process = state.main_process();
  int exit_code = main_process.exit_code();
  if (exit_code != -1) {
    return exit_code;
  }

  // The first command exited with a signal.
  error(0, "the first process crashed! %s",
        main_process.exit_info_to_string().c_str());
}
