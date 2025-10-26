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

int execute(const char *cmd, const char **args, int nargs, int stdio_fd[3], int err2out)
{
	pid_t pid, child_pid;
	int redirect;
	int status;
	int pipe_fd[3][2];
	char **argv;
	int i, dir;

	if ( (argv=(char **) malloc((nargs+2)*sizeof(char *)))==NULL ) return -1;

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
