/*
   runpipe -- run two commands with stdin/stdout bi-directionally connected.

   Idea based on the program dpipe from the Virtual Distributed
   Ethernet package.

   Part of the DOMjudge Programming Contest Jury System and licenced
   under the GNU GPL. See README and COPYING for details.


   Program specifications:

   This program will run two specified commands and connect their
   stdin/stdout to eachother.

   When this program is sent a SIGTERM, this signal is passed to both
   programs. This program will return when both programs are finished
   and reports back the exit code of the first program.
 */

#include "config.h"

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

#define PROGRAM "runpipe"
#define VERSION DOMJUDGE_VERSION "/" REVISION

#include "lib.error.h"
#include "lib.misc.h"

extern int errno;

const char *progname;

int be_verbose;
int show_help;
int show_version;

#define MAX_CMDS 2

int ncmds;
char  *cmd_name[MAX_CMDS];
int    cmd_nargs[MAX_CMDS];
char **cmd_args[MAX_CMDS];
pid_t  cmd_pid[MAX_CMDS];
int    cmd_fds[MAX_CMDS][3];
int    cmd_exit[MAX_CMDS];

int outputmeta;
char *metafilename;
FILE *metafile;

struct option const long_opts[] = {
	{"verbose", no_argument,       NULL,         'v'},
	{"help",    no_argument,       &show_help,    1 },
	{"version", no_argument,       &show_version, 1 },
	{"outmeta", required_argument, NULL,         'M'},
	{ NULL,     0,                 NULL,          0 }
};

void usage()
{
	printf("\
Usage: %s [OPTION]... COMMAND1 [ARGS...] = COMMAND2 [ARGS...]\n\
Run two commands with stdin/stdout bi-directionally connected.\n\
\n\
  -M, --outmeta=FILE   write metadata (runtime, exitcode, etc.) of first program to FILE\n\
  -v, --verbose        display some extra warnings and information\n\
      --help           display this help and exit\n\
      --version        output version information and exit\n\
\n\
Arguments starting with a `=' must be escaped by prepending an extra `='.\n", progname);
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

int main(int argc, char **argv)
{
	struct sigaction sigact;
	sigset_t sigmask;
	pid_t pid;
	int   status;
	int   exitcode, myexitcode;
	int   opt;
	char *arg;
	int   i, newcmd, argsize = 0;
	int   pipe_fd[MAX_CMDS][2];

	progname = argv[0];

	/* Parse command-line options */
	be_verbose = show_help = show_version = 0;
	opterr = 0;
	while ( (opt = getopt_long(argc,argv,"+M:",long_opts,(int *) 0))!=-1 ) {
		switch ( opt ) {
		case 0:   /* long-only option */
			break;
		case 'v': /* verbose option */
			be_verbose = 1;
			break;
		case 'M': /* outputmeta option */
			outputmeta = 1;
			metafilename = strdup(optarg);
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
	ncmds = 0; /* Zero-based index to current command in loop,
	              contains #commands specified after loop */
	newcmd = 1; /* Is current command newly started? */
	for(i=optind; i<argc; i++) {
		/* Check for commands separator */
		if ( strcmp(argv[i],"=")==0 ) {
			ncmds++;
			if ( newcmd ) error(0,"empty command #%d specified", ncmds);
			newcmd = 1;
			if ( ncmds+1>MAX_CMDS ) {
				error(0,"too many commands specified: %d > %d", ncmds+1, MAX_CMDS);
			}
			continue;
		}

		/* Un-escape multiple = at start of argument */
		arg = argv[i];
		if ( strncmp(arg,"==",2)==0 ) arg++;

		if ( newcmd ) {
			newcmd = 0;
			cmd_name[ncmds] = arg;
			cmd_nargs[ncmds] = 0;
			argsize = 5;
			cmd_args[ncmds] = malloc(argsize*sizeof(void *));
			if ( cmd_args[ncmds]==NULL ) error(0,"cannot allocate memory");
		} else {
			if ( cmd_nargs[ncmds]+1>argsize ) {
				argsize += 10;
				cmd_args[ncmds] = realloc(cmd_args[ncmds],argsize*sizeof(void *));
				if ( cmd_args[ncmds]==NULL ) error(0,"cannot allocate memory");
			}
			cmd_args[ncmds][cmd_nargs[ncmds]++] = arg;
		}
	}
	ncmds++;
	if ( newcmd ) error(0,"empty command #%d specified", ncmds);
	if ( ncmds!=2 ) {
		error(0,"%d commands specified, 2 required", ncmds);
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

	/* Create pipes and by default close all file descriptors when
	   executing a forked subcommand, required ones are reset below. */
	for(i=0; i<ncmds; i++) {
		if ( pipe(pipe_fd[i])!=0 ) error(errno,"creating pipes");
		set_fd_close_exec(pipe_fd[i][0], 1);
		set_fd_close_exec(pipe_fd[i][1], 1);
	}

	/* Execute commands as subprocesses and connect pipes as required. */
	for(i=0; i<ncmds; i++) {
		cmd_fds[i][0] = pipe_fd[i][0];
		cmd_fds[i][1] = pipe_fd[1-i][1];
		cmd_fds[i][2] = FDREDIR_NONE;
		set_fd_close_exec(pipe_fd[i][0], 0);
		set_fd_close_exec(pipe_fd[1-i][1], 1);

		cmd_exit[i] = -1;
		cmd_pid[i] = execute(cmd_name[i], (const char **)cmd_args[i],
		                     cmd_nargs[i], cmd_fds[i], 0);
		if ( cmd_pid[i]==-1 ) error(errno,"failed to execute command #%d",i+1);
		verb("started #%d, pid %d: %s",i+1,cmd_pid[i],cmd_name[i]);
	}

	/* Wait for running child commands and check exit status. */
	do {
		pid = waitpid(-1, &status, WNOHANG);
		if ( pid==0 ) pid = waitpid(-1, &status, 0);

		if ( pid<0 ) {
			/* No more child processes, we're done. */
			if ( errno==ECHILD ) break;
			error(errno,"waiting for children");
		}

		for(i=0; i<ncmds; i++) if ( cmd_pid[i]==pid ) {
			if (i == 0 && cmd_exit[1] == -1) {
				/* If the second command hasn't finished yet, then write out metadata. */
				if ( WIFEXITED(status) ) {
					exitcode = WEXITSTATUS(status);
					if ( outputmeta && (metafile = fopen(metafilename,"w"))==NULL ) {
						error(errno,"cannot open `%s'",metafilename);
					}
					write_meta("exitcode","%d",exitcode);
					/* TODO: add more meta data like run time. */
					if ( outputmeta && fclose(metafile)!=0 ) {
						error(errno,"closing file `%s'",metafilename);
					}
				}
			}
			warning(0, "command #%d, pid %d has exited (with status %d)",i+1,pid,status);
			break;
		}
		if ( i>=ncmds ) error(0, "waited for unknown child");

		cmd_exit[i] = status;
		verb("command #%d, pid %d has exited (with status %d)",i+1,pid,status);

		if ( close(cmd_fds[i][0])!=0 || close(cmd_fds[i][1])!=0 ) {
			error(errno,"closing command #%d FD's",i+1);
		}
	} while ( 1 );

	/* Check exit status of commands and report back the exit code of the first. */
	myexitcode = exitcode = 0;
	for(i=0; i<ncmds; i++) {
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
