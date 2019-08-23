/*
 * Miscellaneous common functions for C/C++ programs.
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

#include "config.h"

#include <stdlib.h>
#include <unistd.h>
#include <string.h>
#include <stdarg.h>
#include <signal.h>
#include <sys/wait.h>
#include <fcntl.h>

#include "lib.misc.h"
#include "lib.error.h"

/* Array indices for input/output file descriptors as used by pipe() */
#define PIPE_IN  1
#define PIPE_OUT 0

const int def_stdio_fd[3] = { STDIN_FILENO, STDOUT_FILENO, STDERR_FILENO };

void _alert(const char *libdir, const char *msgtype, const char *description)
{
	static char none[1] = "";
	char *cmd;
	int dummy __attribute__((unused));

	if ( description==NULL ) description = none;

	cmd = allocstr("%s/alert '%s' '%s' &",libdir,msgtype,description);
	logmsg(LOG_INFO,"executing '%s'",cmd);

	/* Assign return value to dummy variable to remove compiler
	 * warnings. We're already trying to generate a warning; there's
	 * no sense in generating another warning when this gives an
	 * error.
	 */
	dummy = system(cmd);

	free(cmd);
}

int execute(const char *cmd, const char **args, int nargs, int stdio_fd[3], int err2out)
{
	pid_t pid, child_pid;
	int redirect;
	int status;
	int pipe_fd[3][2];
	char **argv;
	int i, dir;

	if ( (argv=(char **) malloc((nargs+2)*sizeof(char **)))==NULL ) return -1;

	if ( err2out ) stdio_fd[2] = FDREDIR_NONE;

	redirect = ( stdio_fd[0]!=FDREDIR_NONE ||
	             stdio_fd[1]!=FDREDIR_NONE ||
	             stdio_fd[2]!=FDREDIR_NONE );

	/* Build the complete argument list for execvp.
	 * We can const-cast the pointers, since execvp is guaranteed
	 * not to modify these (or the data pointed to).
	 */
	argv[0] = (char *) cmd;
	for(i=0; i<nargs; i++) argv[i+1] = (char *) args[i];
	argv[nargs+1] = NULL;

	/* Open pipes for IO redirection */
	for(i=0; i<3; i++) {
		if ( stdio_fd[i]==FDREDIR_PIPE && pipe(pipe_fd[i])!=0 ) goto ret_error;
	}

	switch ( child_pid = fork() ) {
	case -1: /* error */
		free(argv);
		return -1;

	case  0: /* child process */
		/* Connect pipes to command stdin/stdout/stderr and close unneeded fd's */
		for(i=0; i<3; i++) {
			if ( stdio_fd[i]==FDREDIR_PIPE ) {
				/* stdin must be connected to the pipe output,
				   stdout/stderr to the pipe input: */
				dir = (i==0 ? PIPE_OUT : PIPE_IN);
				if ( dup2(pipe_fd[i][dir],def_stdio_fd[i])<0 ) goto ret_error;
				if ( close(pipe_fd[i][dir])!=0 ) goto ret_error;
				if ( close(pipe_fd[i][1-dir])!=0 ) goto ret_error;
			}
			if ( stdio_fd[i]>=0 ) {
				if ( dup2(stdio_fd[i],def_stdio_fd[i])<0 ) goto ret_error;
				if ( close(stdio_fd[i])!=0 ) goto ret_error;
			}
		}
		/* Redirect stderr to stdout */
		if ( err2out && dup2(STDOUT_FILENO,STDERR_FILENO)<0 ) goto ret_error;

		/* Replace child with command */
		execvp(cmd,argv);
		abort();

	default: /* parent process */

		free(argv);

		/* Set and close file descriptors */
		for(i=0; i<3; i++) {
			if ( stdio_fd[i]==FDREDIR_PIPE ) {
				/* parent process output must connect to the input of
				   the pipe to child, and vice versa for stdout/stderr: */
				dir = (i==0 ? PIPE_IN : PIPE_OUT);
				stdio_fd[i] = pipe_fd[i][dir];
				if ( close(pipe_fd[i][1-dir])!=0 ) return -1;
			}
		}

		/* Return if some IO is redirected to be able to read/write to child */
		if ( redirect ) return child_pid;

		/* Wait for the child command to finish */
		while ( (pid = wait(&status))!=-1 && pid!=child_pid );
		if ( pid!=child_pid ) return -1;

		/* Test whether command has finished abnormally */
		if ( ! WIFEXITED(status) ) {
			if ( WIFSIGNALED(status) ) return 128+WTERMSIG(status);
			if ( WIFSTOPPED (status) ) return 128+WSTOPSIG(status);
			return -2;
		}
		return WEXITSTATUS(status);
	}

	/* This should never be reached */
	return -2;

	/* Handle resources before returning on error */
  ret_error:
	free(argv);
	return -1;
}

int exitsignalled;

void sig_handler(int sig)
{
	logmsg(LOG_DEBUG, "Signal %d received", sig);

	switch ( sig ) {
	case SIGTERM:
	case SIGHUP:
	case SIGINT:
		exitsignalled = 1;
		break;
	}
}

void initsignals()
{
	struct sigaction sa;
	sigset_t newmask, oldmask;

	exitsignalled = 0;

	/* unmask all signals */
	memset(&newmask, 0, sizeof(newmask));
	if ( sigprocmask(SIG_SETMASK, &newmask, &oldmask)!=0 ) {
		error(errno,"unmasking signals");
	}

	logmsg(LOG_DEBUG, "Installing signal handlers");

	sa.sa_handler = &sig_handler;
	sa.sa_mask = newmask;
	sa.sa_flags = 0;

	if ( sigaction(SIGTERM,&sa,NULL)!=0 ) error(errno,"installing signal handler");
	if ( sigaction(SIGHUP ,&sa,NULL)!=0 ) error(errno,"installing signal handler");
	if ( sigaction(SIGINT ,&sa,NULL)!=0 ) error(errno,"installing signal handler");
}


char *pidfile;

/* Function to remove PID file at process exit. */
void remove_pidfile()
{
	unlink(pidfile);
}

void daemonize(const char *_pidfile)
{
	pid_t pid;
	int fd, maxfd;
	char str[15];

	switch ( pid = fork() ) {
	case -1: error(errno, "cannot fork daemon");
	case  0: break;     /* child process: do nothing here. */
	default: _exit(0);  /* parent process: exit. */
	}

	/* Check and write PID to file */
	if ( _pidfile!=NULL ) {
		pidfile = strdup(_pidfile);
		if ( (fd=open(pidfile, O_RDWR|O_CREAT|O_EXCL, 0640))<0 ) {
			error(errno, "cannot create pidfile '%s'", pidfile);
		}
		sprintf(str, "%d\n", pid);
		if ( write(fd, str, strlen(str))<(ssize_t)strlen(str) ) {
			error(errno, "failed writing PID to file");
		}
		if ( close(fd)!=0 ) error(errno, "closing pidfile '%s'", pidfile);
		atexit(remove_pidfile);
	}

	/* Notify user with daemon PID before detaching from TTY. */
	logmsg(LOG_NOTICE, "daemonizing with PID = %d", pid);

	/* Reopen std{in,out,err} file descriptors to /dev/null.
	   Closing them gives error when the daemon or a child process
	   tries to read/write to them. */
	if ( freopen("/dev/null", "r", stdin )!=NULL ||
	     freopen("/dev/null", "w", stdout)!=NULL ||
	     freopen("/dev/null", "w", stderr)!=NULL ) {
		error(errno, "cannot reopen stdio files to /dev/null");
	}

	/* Close all other file descriptors. */
	maxfd = sysconf(_SC_OPEN_MAX);
	for(fd=3; fd<maxfd; fd++) close(fd);

	/* Start own process group, detached from any tty */
	if ( setsid()<0 ) error(errno, "cannot set daemon process group");
}

char *stripendline(char *str)
{
	size_t i, j;

	for(i=0, j=0; str[i]!=0; i++) {
		if ( ! (str[i]=='\n' || str[i]=='\r') ) str[j++] = str[i];
	}

	str[j] = 0;

	return str;
}

void version(const char *prog, const char *vers)
{
	printf("\
%s -- part of DOMjudge version %s\n\
Written by the DOMjudge developers\n\n\
DOMjudge comes with ABSOLUTELY NO WARRANTY.  This is free software, and you\n\
are welcome to redistribute it under certain conditions.  See the GNU\n\
General Public Licence for details.\n", prog, vers);
	exit(0);
}
