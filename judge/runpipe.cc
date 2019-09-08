/*
   runpipe -- run two commands with stdin/stdout bi-directionally connected.

   Idea based on the program dpipe from the Virtual Distributed
   Ethernet package.

   Part of the DOMjudge Programming Contest Jury System and licensed
   under the GNU GPL. See README and COPYING for details.


   Program specifications:

   This program will run two specified commands and connect their
   stdin/stdout to eachother.

   When this program is sent a SIGTERM, this signal is passed to both
   programs. This program will return when both programs are finished
   and reports back the exit code of the first program.
 */

#include "config.h"

/* For Linux specific fcntl F_SETPIPE_SZ command. */
#if __gnu_linux__
#define PROC_MAX_PIPE_SIZE "/proc/sys/fs/pipe-max-size"
#endif

#include <sys/select.h>
#include <sys/time.h>
#include <sys/types.h>
#include <sys/wait.h>
#include <errno.h>
#include <signal.h>
#include <stdlib.h>
#include <unistd.h>
#include <fcntl.h>
#include <string.h>
#include <stdarg.h>
#include <stdio.h>
#include <getopt.h>

#include <string>
#include <vector>

#define PROGRAM "runpipe"
#define VERSION DOMJUDGE_VERSION "/" REVISION

#include "lib.error.h"
#include "lib.misc.h"

using namespace std;

/* Use the POSIX minimum for PIPE_BUF. */
#define BUF_SIZE 512

extern int errno;

const char *progname;

int be_verbose;
int show_help;
int show_version;

const int num_cmds = 2;

string cmd_name[num_cmds];
vector<string> cmd_args[num_cmds];
pid_t  cmd_pid[num_cmds];
int    cmd_fds[num_cmds][3];
int    cmd_exit[num_cmds];

int write_progout;
char *progoutfilename;
FILE *progoutfile;

int outputmeta;
char *metafilename;
FILE *metafile;
int validator_exited_first;
int submission_still_alive;

struct timeval start_time;

struct option const long_opts[] = {
	{"verbose", no_argument,       NULL,         'v'},
	{"help",    no_argument,       &show_help,    1 },
	{"version", no_argument,       &show_version, 1 },
	{"outprog", required_argument, NULL,         'o'},
	{"outmeta", required_argument, NULL,         'M'},
	{ NULL,     0,                 NULL,          0 }
};

void usage()
{
	printf("\
Usage: %s [OPTION]... COMMAND1 [ARGS...] = COMMAND2 [ARGS...]\n\
Run two commands with stdin/stdout bi-directionally connected.\n\
\n", progname);
	printf("\
  -o, --outprog=FILE   write stdout from second program to FILE\n\
  -M, --outmeta=FILE   write metadata (runtime, exitcode, etc.) of first program to FILE\n\
  -v, --verbose        display some extra warnings and information\n\
      --help           display this help and exit\n\
      --version        output version information and exit\n\
\n\
Arguments starting with a `=' must be escaped by prepending an extra `='.\n");
	exit(0);
}

void verb(const char *, ...) __attribute__((format (printf, 1, 2)));

void verb(const char *format, ...)
{
	va_list ap;
	va_start(ap,format);

	if ( be_verbose ) {
		fprintf(stderr,"%s: verbose: ",progname);
		vfprintf(stderr,format,ap);
		fprintf(stderr,"\n");
	}

	va_end(ap);
}

int execute(string cmd, vector<string> args, int stdio_fd[3], int err2out)
{
	const char **argv;

	if ( (argv = (const char **)calloc(args.size(), sizeof(char *)))==NULL ) return -1;
	for(size_t i=0; i<args.size(); i++) argv[i] = args[i].c_str();

	int pid = execute(cmd.c_str(), argv, args.size(), stdio_fd, err2out);
	free(argv);
	return pid;
}

string join(char separator, vector<string> strings)
{
	string res = strings[0];
	for(size_t i=1; i<strings.size(); i++) res += separator + strings[i];
	return res;
}

void write_meta(const char *key, const char *format, ...)
{
	va_list ap;

	if ( !outputmeta ) return;

	va_start(ap,format);

	if ( fprintf(metafile,"%s: ",key)<=0 ) {
		error(0,"cannot write to file `%s'",metafilename);
	}
	if ( vfprintf(metafile,format,ap)<0 ) {
		error(0,"cannot write to file `%s'(vfprintf)",metafilename);
	}
	if ( fprintf(metafile,"\n")<=0 ) {
		error(0,"cannot write to file `%s'",metafilename);
	}

	va_end(ap);
}

void terminate(int sig)
{
	struct sigaction sigact;

	/* Reset signal handlers to default */
	sigact.sa_handler = SIG_DFL;
	sigact.sa_flags = 0;
	if ( sigemptyset(&sigact.sa_mask)!=0 ) {
		warning(0,"could not initialize signal mask");
	}

	if ( sigaction(SIGTERM,&sigact,NULL)!=0 ) {
		warning(0,"could not restore signal handler");
	}

	/* Send kill signal to all children */
	verb("sending SIGTERM");
	if ( kill(0,SIGTERM)!=0 ) error(errno,"sending SIGTERM");
}

void set_fd_close_exec(int fd, int value)
{
	long  fdflags;

	fdflags = fcntl(fd, F_GETFD);
	if ( fdflags==-1 ) error(errno,"reading filedescriptor flags");
	if ( value ) {
		fdflags = fdflags | FD_CLOEXEC;
	} else {
		fdflags = fdflags & ~FD_CLOEXEC;
	}
	if ( fcntl(fd, F_SETFD, fdflags)!=0 ) {
		error(errno,"setting filedescriptor flags");
	}
}

/* Try to resize pipes to their maximum size on Linux.
   We do this to make it as unlikely as possible for either the jury
   or team program to get blocked writing to the other side, if that
   side doesn't consume data from the pipe. See also:
   https://github.com/Kattis/problemtools/issues/113

   max_pipe_size == -1 means uninitialized, and -2 means that we
   couldn't read from the proc file.
 */
#ifdef PROC_MAX_PIPE_SIZE
int max_pipe_size = -1;

void resize_pipe(int fd)
{
	FILE *f;
	int r;

	if ( max_pipe_size<=-2 ) return;
	if ( max_pipe_size==-1 ) {
		if ( (f = fopen(PROC_MAX_PIPE_SIZE, "r"))==NULL ) {
			max_pipe_size = -2;
			warning(errno, "could not open '%s'", PROC_MAX_PIPE_SIZE);
			return;
		}
		if ( fscanf(f, "%d", &max_pipe_size)!=1 ) {
			max_pipe_size = -2;
			warning(errno, "could not read from '%s'", PROC_MAX_PIPE_SIZE);
			return;
		}
		if ( fclose(f)!=0 ) {
			warning(errno, "could not close '%s'", PROC_MAX_PIPE_SIZE);
		}
	}

	r = fcntl(fd, F_SETPIPE_SZ, max_pipe_size);
	if ( r==-1 ) {
		warning(errno, "could not change pipe size");
	}
	verb("set pipe fd %d to size %d", fd, r);
}
#endif

void pump_pipes(int *fd_out, int *fd_in, int from_val)
{
	ssize_t nread, to_write, nwritten;
	fd_set readfds;
	char buf[BUF_SIZE+1];
	int r;
	struct timeval tv;
	double diff;

	if ( *fd_out<0 ) return;

	FD_ZERO(&readfds);
	FD_SET(*fd_out, &readfds);

	tv.tv_sec = 0;
	tv.tv_usec = 1000; /* FIXME: this is just in order to not block */
	r = select(*fd_out+1, &readfds, NULL, NULL, &tv);
	if ( r==-1 && errno!=EINTR ) error(errno,"waiting for child data");
	gettimeofday(&tv, NULL);
	diff = (double)(tv.tv_usec - start_time.tv_usec) / 1000000
		+ (double)(tv.tv_sec - start_time.tv_sec);

	if ( FD_ISSET(*fd_out, &readfds) ) {
		nread = read(*fd_out, buf, BUF_SIZE);
		verb("read %d bytes from program #2, fd %d", (int)nread, *fd_out);
		if ( nread>0 ) {
			buf[nread] = 0;
			/* First write to file. */
			errno = 0;
			fprintf(progoutfile, "[ %6.3fs/%ld]%c: ", diff, nread, from_val ? '>' : '<');
			nwritten = fwrite(buf, 1, nread, progoutfile);
			fprintf(progoutfile, "\n");
			if ( nwritten<nread ) {
				error(errno,"writing to `%s'", progoutfilename);
			}
			if ( fflush(progoutfile)!=0 ) {
				error(errno,"flushing `%s'", progoutfilename);
			}
			/* Then write to the pipe connecting to program #1. */
			to_write = nread;
			while ( to_write>0 && *fd_in>=0 ) {
				nwritten = write(*fd_in, buf+(nread-to_write), to_write);
				if ( nwritten==-1 ) {
					if ( errno == EPIPE ) {
						/* The receiving process already closed the pipe. */
						if ( close(*fd_in) !=0 ) {
							error(errno,"closing pipe for fd %d", *fd_in);
						}
						*fd_in = -1;
						break;
					} else {
						error(errno,"writing to fd %d", *fd_in);
					}
				}
				verb("wrote %d bytes to fd %d", (int)nwritten, *fd_in);
				to_write -= nwritten;
			}
		}
		if ( nread==-1 ) {
			if (errno == EINTR || errno == EAGAIN || errno == EWOULDBLOCK) return;
			error(errno,"copying data from fd %d", *fd_out);
		}
		if ( nread==0 ) {
			warning(0, "pipe of #%d is empty", 1 + 1-from_val);
			/* in validator: before we close the pipe
			 * (on which the submission may either crash or act weirdly)
			 * let's check if the submission is already done
			 */
			if ( from_val ) {
				if ( submission_still_alive ) {
					warning(0, "validator exited first");
					validator_exited_first = 1;
				}
			} else {
				submission_still_alive = 0;
			}
			/* EOF detected: close input/output pipe fds. */
			if ( *fd_out>=0 && close(*fd_out)!=0 ) {
				error(errno,"closing pipe for fd %d", *fd_out);
			}
			if ( *fd_in >=0 && close(*fd_in) !=0 ) {
				error(errno,"closing pipe for fd %d", *fd_in);
			}
			*fd_out = -1;
			*fd_in  = -1;
		}
	}
}

int main(int argc, char **argv)
{
	struct sigaction sigact;
	sigset_t sigmask;
	pid_t pid;
	int   status;
	int   exitcode, myexitcode;
	int   opt;
	int   i, r, fd_out = 0;
	int   pipe_fd[num_cmds][2];
	int   progout_pipe_fd[num_cmds][2];

	progname = argv[0];

	/* Parse command-line options */
	be_verbose = show_help = show_version = 0;
	opterr = 0;
	while ( (opt = getopt_long(argc,argv,"+o:M:v",long_opts,(int *) 0))!=-1 ) {
		switch ( opt ) {
		case 0:   /* long-only option */
			break;
		case 'v': /* verbose option */
			be_verbose = 1;
			verb("verbose mode enabled");
			break;
		case 'o': /* outprog option */
			write_progout = 1;
			progoutfilename = strdup(optarg);
			verb("writing program #2 output to '%s'", progoutfilename);
			break;
		case 'M': /* outmeta option */
			outputmeta = 1;
			metafilename = strdup(optarg);
			verb("writing metadata to '%s'", metafilename);
			break;
		case ':': /* getopt error */
		case '?':
			error(0,"unknown option or missing argument `%c'",optopt);
			break;
		default:
			error(0,"getopt returned character code `%c' ??",(char)opt);
		}
	}

	if ( show_help ) usage();
	if ( show_version ) version(PROGRAM,VERSION);

	if ( argc<=optind ) error(0,"no command specified");

	/* Parse commands to be executed */
	int ncmds = 0; /* Zero-based index to current command in loop,
	                  contains #commands specified after loop */
	int newcmd = 1; /* Is current command newly started? */
	for(i=optind; i<argc; i++) {
		string arg(argv[i]);

		/* Check for commands separator */
		if ( arg=="=" ) {
			ncmds++;
			if ( newcmd ) error(0,"empty command #%d specified", ncmds);
			newcmd = 1;
			if ( ncmds+1>num_cmds ) {
				error(0,"too many commands specified: %d > %d", ncmds+1, num_cmds);
			}
			continue;
		}

		/* Un-escape multiple = at start of argument */
		if ( arg.substr(0,2)=="==" ) arg = arg.substr(1);

		if ( newcmd ) {
			newcmd = 0;
			cmd_name[ncmds] = arg;
			cmd_args[ncmds] = vector<string>();
		} else {
			cmd_args[ncmds].push_back(arg);
		}
	}
	ncmds++;
	if ( newcmd ) error(0,"empty command #%d specified", ncmds);
	if ( ncmds!=num_cmds ) {
		error(0,"%d commands specified, %d required", ncmds, num_cmds);
	}

	/* Install TERM signal handler */
	if ( sigemptyset(&sigmask)!=0 ) error(errno,"creating signal mask");
	if ( sigprocmask(SIG_SETMASK, &sigmask, NULL)!=0 ) {
		error(errno,"unmasking signals");
	}
	if ( sigaddset(&sigmask,SIGTERM)!=0 ) error(errno,"setting signal mask");

	sigact.sa_handler = terminate;
	sigact.sa_flags   = SA_RESETHAND | SA_RESTART;
	sigact.sa_mask    = sigmask;
	if ( sigaction(SIGTERM,&sigact,NULL)!=0 ) {
		error(errno,"installing signal handler");
	}

	validator_exited_first = 0;
	submission_still_alive = 1;

	/* Create pipes and by default close all file descriptors when
	   executing a forked subcommand, required ones are reset below. */
	for(i=0; i<num_cmds; i++) {
		if ( pipe(pipe_fd[i])!=0 ) error(errno,"creating pipes");
		verb("command #%d: read fd=%d, write fd=%d", i, pipe_fd[i][0], pipe_fd[i][1]);
		set_fd_close_exec(pipe_fd[i][0], 1);
		set_fd_close_exec(pipe_fd[i][1], 1);
#ifdef PROC_MAX_PIPE_SIZE
		resize_pipe(pipe_fd[i][1]);
#endif
	}

	/* Setup file and extra pipe for writing program output. */
	if ( write_progout ) {
		if ( (progoutfile = fopen(progoutfilename,"w"))==NULL ) {
			error(errno,"cannot open `%s'",progoutfilename);
		}
		for(i=0; i<num_cmds; i++) {
			if ( pipe(progout_pipe_fd[i])!=0 ) error(errno,"creating pipes");
			set_fd_close_exec(progout_pipe_fd[i][0], 1);
			set_fd_close_exec(progout_pipe_fd[i][1], 1);
#ifdef PROC_MAX_PIPE_SIZE
			resize_pipe(progout_pipe_fd[i][1]);
#endif
			verb("writing program #%d output via pipe %d -> %d",
			     i+1, progout_pipe_fd[i][1], progout_pipe_fd[i][0]);
		}
	}

	/* Execute commands as subprocesses and connect pipes as required. */
	for(i=0; i<num_cmds; i++) {
		fd_out = write_progout ? progout_pipe_fd[i][1] : pipe_fd[1-i][1];
		cmd_fds[i][0] = pipe_fd[i][0];
		cmd_fds[i][1] = fd_out;
		cmd_fds[i][2] = FDREDIR_NONE;
		verb("pipes for command #%d are %d and %d", i+1, cmd_fds[i][0], cmd_fds[i][1]);

		set_fd_close_exec(pipe_fd[i][0], 0);
		set_fd_close_exec(fd_out, 0);

		cmd_exit[i] = -1;
		cmd_pid[i] = execute(cmd_name[i], cmd_args[i], cmd_fds[i], 0);
		if ( cmd_pid[i]==-1 ) error(errno,"failed to execute command #%d",i+1);
		string args_str = join(' ', cmd_args[i]);
		verb("started #%d, pid %d: %s %s",i+1,cmd_pid[i],cmd_name[i].c_str(),args_str.c_str());

		set_fd_close_exec(pipe_fd[i][0], 1);
		set_fd_close_exec(fd_out, 1);
	}
	gettimeofday(&start_time, NULL);

	if ( write_progout ) {
		if ( close(pipe_fd[1][0])!=0 ) error(errno,"closing pipe read end");
		if ( close(pipe_fd[0][0])!=0 ) error(errno,"closing pipe read end");
		if ( close(progout_pipe_fd[0][1])!=0 ) error(errno,"closing pipe write end");
		if ( close(progout_pipe_fd[1][1])!=0 ) error(errno,"closing pipe write end");
	} else {
		for(i=0; i<num_cmds; i++) {
			if ( close(pipe_fd[i][0])!=0 ) error(errno,"closing pipe read end");
			if ( close(pipe_fd[i][1])!=0 ) error(errno,"closing pipe write end");
		}
	}

	/* Wait for running child commands and check exit status. */
	while ( 1 ) {

		if ( write_progout ) {
			for(i=0; i<num_cmds; i++) {
				pump_pipes(&progout_pipe_fd[i][0], &pipe_fd[1-i][1], 1-i);
			}

			pid = 0;
			for(i=0; i<num_cmds; i++) {
				if ( cmd_exit[i]==-1 ) {
					pid = waitpid(cmd_pid[i], &status, WNOHANG);
					if ( pid != 0 ) break;
				}
			}
			if ( pid==0 ) continue;
		} else {
			pid = waitpid(-1, &status, 0);
		}

		if ( pid<0 ) {
			/* No more child processes, we're done. */
			if ( errno==ECHILD ) break;
			error(errno,"waiting for children");
		}

		/* Pump pipes one more time to improve detection which program exited first. */
		if ( write_progout ) {
			for(i=0; i<num_cmds; i++) {
				pump_pipes(&progout_pipe_fd[i][0], &pipe_fd[1-i][1], 1-i);
			}
		}

		for(i=0; i<num_cmds; i++) if ( cmd_pid[i]==pid ) {
			if (i == 1) {
				submission_still_alive = 0;
			}
			warning(0, "command #%d, pid %d has exited (with status %d)",i+1,pid,status);
			break;
		}
		if ( i>=num_cmds ) error(0, "waited for unknown child");

		cmd_exit[i] = status;
		verb("command #%d, pid %d has exited (with status %d)",i+1,pid,status);
		if (cmd_exit[0] != -1 && cmd_exit[1] != -1) {
			/* Both child processes are done. */
			if (validator_exited_first && WIFEXITED(cmd_exit[0])) {
				exitcode = WEXITSTATUS(cmd_exit[0]);
				if ( outputmeta && (metafile = fopen(metafilename,"w"))==NULL ) {
					error(errno,"cannot open `%s'",metafilename);
				}
				write_meta("exitcode","%d", exitcode);
				/* TODO: add more meta data like run time. */
				if ( outputmeta && fclose(metafile)!=0 ) {
					error(errno,"closing file `%s'",metafilename);
				}
			}
			break;
		}
	};

	/* Reset pipe filedescriptors to use blocking I/O. */
	for(i=0; i<num_cmds; i++) {
		if ( write_progout && progout_pipe_fd[i][0]>=0 ) {
			r = fcntl(progout_pipe_fd[i][0], F_GETFL);
			if (r == -1) error(errno, "fcntl, getting flags");

			r = fcntl(progout_pipe_fd[i][0], F_SETFL, r ^ O_NONBLOCK);
			if (r == -1) error(errno, "fcntl, setting flags");

			do {
				pump_pipes(&progout_pipe_fd[i][0], &pipe_fd[1-i][1], 1-i);
			} while ( progout_pipe_fd[i][0]>=0 );
		}
	}

	/* Check exit status of commands and report back the exit code of the first. */
	myexitcode = exitcode = 0;
	for(i=0; i<num_cmds; i++) {
		if ( cmd_exit[i]!=0 ) {
			status = cmd_exit[i];
			/* Test whether command has finished abnormally */
			if ( ! WIFEXITED(status) ) {
				if ( WIFSIGNALED(status) ) {
					warning(0,"command #%d terminated with signal %d",i+1,WTERMSIG(status));
					exitcode = 128+WTERMSIG(status);
				} else {
					error(0,"command #%d exit status unknown: %d",i+1,status);
				}
			} else {
				/* Log the exitstatus of the failed commands */
				exitcode = WEXITSTATUS(status);
				if ( exitcode!=0 ) {
					warning(0,"command #%d exited with exitcode %d",i+1,exitcode);
				}
			}
			/* Only report it for the first command. */
			if ( i==0 ) myexitcode = exitcode;
		}
	}

	return myexitcode;
}
