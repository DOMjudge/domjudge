/*
   runpipe -- run two commands with stdin/stdout bi-directionally connected.
   Copyright (C) 2010 Jaap Eldering (eldering@a-eskwadraat.nl).

   Idea based on the program dpipe from the Virtual Distributed
   Ethernet package.

   $Id$

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2, or (at your option)
   any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software Foundation,
   Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.


   Program specifications:

   This program will run two specified commands and connect their
   stdin/stdout to eachother.

   When this program is sent a SIGTERM, this signal is passed to both
   programs. This program will return when both programs are finished
   and if either of the programs had non-zero exitcode, the first
   non-zero exitcode will be returned.
 */

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
#define VERSION "$Rev$"
#define AUTHORS "Jaap Eldering"

#include "lib.error.h"
#include "lib.misc.h"

const struct timespec killdelay = { 0, 100000000L }; /* 0.1 seconds */

extern int errno;

char  *progname;
char  *outputfilename;

int show_help;
int show_version;

#define MAX_CMDS 2

int ncmds;
char  *cmd_name[MAX_CMDS];
int    cmd_nargs[MAX_CMDS];
char **cmd_args[MAX_CMDS];
pid_t  cmd_pid[MAX_CMDS];
int    cmd_fds[MAX_CMDS][3];


struct timeval starttime, endtime;

struct option const long_opts[] = {
	{"output",  required_argument, NULL,         'o'},
	{"help",    no_argument,       &show_help,    1 },
	{"version", no_argument,       &show_version, 1 },
	{ NULL,     0,                 NULL,          0 }
};

void version()
{
	printf("\
%s -- version %s\n\
Written by %s\n\n\
%s comes with ABSOLUTELY NO WARRANTY.  This is free software, and you\n\
are welcome to redistribute it under certain conditions.  See the GNU\n\
General Public Licence for details.\n",PROGRAM,VERSION,AUTHORS,PROGRAM);
	exit(0);
}

void usage()
{
	printf("\
Usage: %s [OPTION]... COMMAND1 [ARGS...] = COMMANDD2 [ARGS...]\n\
Run two commands with stdin/stdout bi-directionally connected.\n\
\n\
      --help           display this help and exit\n\
      --version        output version information and exit\n\
\n\
Arguments starting with a `=' must be escaped by prepending an extra `='.\n", progname);
	exit(0);
}

#if 0
void terminate(int sig)
{
	struct sigaction sigact;

	/* Reset signal handlers to default */
	sigact.sa_handler = SIG_DFL;
	if ( sigaction(SIGTERM,&sigact,NULL)!=0 ) warning("error restoring signal handler");
	if ( sigaction(SIGALRM,&sigact,NULL)!=0 ) warning("error restoring signal handler");

	if ( sig==SIGALRM ) {
		warning("timelimit reached: aborting command");
	} else {
		warning("received signal %d: aborting command",sig);
	}

	/* First try to kill graciously, then hard */
	verbose("sending SIGTERM");
	if ( kill(-child_pid,SIGTERM)!=0 ) error(errno,"sending SIGTERM to command");

	/* Prefer nanosleep over sleep because of higher resolution and
	   it does not interfere with signals. */
	nanosleep(&killdelay,NULL);

	verbose("sending SIGKILL");
	if ( kill(-child_pid,SIGKILL)!=0 ) error(errno,"sending SIGKILL to command");
}

inline long readoptarg(const char *desc, long minval, long maxval)
{
	long arg;
	char *ptr;

	arg = strtol(optarg,&ptr,10);
	if ( errno || *ptr!='\0' || arg<minval || arg>maxval ) {
		error(errno,"invalid %s specified: `%s'",desc,optarg);
	}

	return arg;
}
#endif

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
// 	sigset_t sigmask;
 	pid_t pid;
	int   status;
//	int   exitcode;
//	char *ptr;
	int   opt;
	char *arg;
	int   i, newcmd, argsize = 0;
	int   pipe_fd[MAX_CMDS][2];

// 	struct itimerval itimer;
// 	struct sigaction sigact;

	progname = argv[0];

	/* Parse command-line options */
	show_help = show_version = 0;
	opterr = 0;
	while ( (opt = getopt_long(argc,argv,"+v",long_opts,(int *) 0))!=-1 ) {
		switch ( opt ) {
		case 0:   /* long-only option */
			break;
// 		case 'o': /* output option */
// 			use_output = 1;
// 			outputfilename = strdup(optarg);
// 			break;
		case ':': /* getopt error */
		case '?':
			error(0,"unknown option or missing argument `%c'",optopt);
			break;
		default:
			error(0,"getopt returned character code `%c' ??",(char)opt);
		}
	}

	if ( show_help ) usage();
	if ( show_version ) version();

	if ( argc<=optind ) error(0,"no command specified");

	/* Parse commands to be executed */
	ncmds = 0;
	newcmd = 1;
	for(i=optind; i<argc; i++) {
		/* Check for commands separator */
		if ( strcmp(argv[i],"=")==0 ) {
			ncmds++;
			if ( newcmd ) error(0,"empty command #%d specified", ncmds);
			newcmd = 1;
			if ( ncmds>MAX_CMDS ) {
				error(0,"too many commands specified: %d > %d", ncmds, MAX_CMDS);
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
	if ( ncmds>MAX_CMDS ) {
		error(0,"too many commands specified: %d > %d", ncmds, MAX_CMDS);
	}
	if ( ncmds!=2 ) {
		error(0,"%d commands specified, 2 required", ncmds);
	}

	/* Create pipes and by default close all file descriptors when
	   executing a forked subcommand, required ones are reset below. */
	for(i=0; i<ncmds; i++) {
		if ( pipe(pipe_fd[i])!=0 ) error(errno,"creating pipes");
		set_fd_close_exec(pipe_fd[i][0], 1);
		set_fd_close_exec(pipe_fd[i][1], 1);
	}

	for(i=0; i<ncmds; i++) {
		cmd_fds[i][0] = pipe_fd[i][0];
		cmd_fds[i][1] = pipe_fd[1-i][1];
		cmd_fds[i][2] = FDREDIR_NONE;
		set_fd_close_exec(pipe_fd[i][0], 0);
		set_fd_close_exec(pipe_fd[1-i][1], 1);

		cmd_pid[i] = execute(cmd_name[i], cmd_args[i], cmd_nargs[i], cmd_fds[i], 0);
		if ( cmd_pid[i]==-1 ) error(errno,"failed to execute command #%d",i+1);
		printf("started #%d, pid %d: %s\n",i+1,cmd_pid[i],cmd_name[i]);
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

		for(i=0; i<ncmds; i++) if ( cmd_pid[i]==pid ) break;

		printf("command #%d, pid %d has exited with status %d\n",i+1,pid,status);

		if ( close(cmd_fds[i][0])!=0 || close(cmd_fds[i][1])!=0 ) {
			error(errno,"closing command #%d FD's",i+1);
		}
	} while ( 1 );

#if 0
	switch ( 1 ) {
	default: /* become watchdog */

		/* Shed privileges, only if not using a separate child uid,
		   because in that case we may need root privileges to kill
		   the child process. Do not use Linux specific setresuid()
		   call with saved set-user-ID. */
		if ( !use_user ) {
			if ( setuid(getuid())!=0 ) error(errno, "setting watchdog uid");
			verbose("watchdog using user ID `%d'",getuid());
		}

		if ( gettimeofday(&starttime,NULL) ) error(errno,"getting time");

		/* unmask all signals */
		if ( sigemptyset(&sigmask)!=0 ) error(errno,"creating signal mask");
		if ( sigprocmask(SIG_SETMASK, &sigmask, NULL)!=0 ) {
			error(errno,"unmasking signals");
		}

		/* Construct one-time signal handler to terminate() for TERM
		   and ALRM signals. */
		if ( sigaddset(&sigmask,SIGALRM)!=0 ||
		     sigaddset(&sigmask,SIGTERM)!=0 ) error(errno,"setting signal mask");

		sigact.sa_handler = terminate;
		sigact.sa_flags   = SA_RESETHAND | SA_RESTART;
		sigact.sa_mask    = sigmask;

		/* Kill child command when we receive SIGTERM */
		if ( sigaction(SIGTERM,&sigact,NULL)!=0 ) {
			error(errno,"installing signal handler");
		}

		if ( use_time ) {
			/* Kill child when we receive SIGALRM */
			if ( sigaction(SIGALRM,&sigact,NULL)!=0 ) {
				error(errno,"installing signal handler");
			}

			/* Trigger SIGALRM via setitimer:  */
			itimer.it_interval.tv_sec  = 0;
			itimer.it_interval.tv_usec = 0;
			itimer.it_value.tv_sec  = runtime / 1000000;
			itimer.it_value.tv_usec = runtime % 1000000;

			if ( setitimer(ITIMER_REAL,&itimer,NULL)!=0 ) {
				error(errno,"setting timer");
			}
			verbose("using timelimit of %.3lf seconds",runtime*1E-6);
		}

		/* Wait for the child command to finish */
		while ( (pid = wait(&status))!=-1 && pid!=child_pid );
		if ( pid!=child_pid ) error(errno,"waiting on child");

		/* Drop root before writing to output file. */
		if ( setuid(getuid())!=0 ) error(errno,"dropping root privileges");

		outputtime();

		/* Test whether command has finished abnormally */
		if ( ! WIFEXITED(status) ) {
			if ( WIFSIGNALED(status) ) {
				warning("command terminated with signal %d",WTERMSIG(status));
				return 128+WTERMSIG(status);
			}
			if ( WIFSTOPPED(status) ) {
				warning("command stopped with signal %d",WSTOPSIG(status));
				return 128+WSTOPSIG(status);
			}
			error(0,"command exit status unknown: %d",status);
		}

		/* Return the exitstatus of the command */
		exitcode = WEXITSTATUS(status);
		if ( exitcode!=0 ) verbose("command exited with exitcode %d",exitcode);
		return exitcode;
	}

	/* This should never be reached */
	error(0,"unexpected end of program");
#endif

	return 0;
}
