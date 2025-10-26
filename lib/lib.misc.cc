/*
 * Miscellaneous common functions for C/C++ programs.
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

#include "config.h"

#include <cstdlib>
#include <unistd.h>
#include <cstring>
#include <cstdarg>
#include <csignal>
#include <sys/wait.h>
#include <fcntl.h>
#include <vector>
#include <iostream>

#include "lib.misc.h"
#include "lib.error.h"

/* Array indices for input/output file descriptors as used by pipe() */
constexpr int PIPE_IN = 1;
constexpr int PIPE_OUT = 0;

const int def_stdio_fd[3] = { STDIN_FILENO, STDOUT_FILENO, STDERR_FILENO };

int execute(const std::string& cmd, const std::vector<std::string>& args,
            std::array<int, 3>& stdio_fd, bool err2out)
{
	if ( err2out ) stdio_fd[2] = FDREDIR_NONE;

	const bool redirect = ( stdio_fd[0]!=FDREDIR_NONE ||
	                        stdio_fd[1]!=FDREDIR_NONE ||
	                        stdio_fd[2]!=FDREDIR_NONE );

	/* Build the complete argument list for execvp.
	 * We can const-cast the pointers, since execvp is guaranteed
	 * not to modify these (or the data pointed to).
	 */
	std::vector<char *> argv;
	argv.push_back(const_cast<char*>(cmd.c_str()));
	for (const auto& arg : args) {
		argv.push_back(const_cast<char*>(arg.c_str()));
	}
	argv.push_back(nullptr);

	int pipe_fd[3][2];
	/* Open pipes for IO redirection */
	for(int i=0; i<3; i++) {
		if ( stdio_fd[i]==FDREDIR_PIPE && pipe(pipe_fd[i])!=0 ) return -1;
	}

	pid_t child_pid;
	switch ( child_pid = fork() ) {
	case -1: /* error */
		return -1;

	case  0: /* child process */
		/* Connect pipes to command stdin/stdout/stderr and close unneeded fd's */
		for(int i=0; i<3; i++) {
			if ( stdio_fd[i]==FDREDIR_PIPE ) {
				/* stdin must be connected to the pipe output,
				   stdout/stderr to the pipe input: */
				const int dir = (i==0 ? PIPE_OUT : PIPE_IN);
				if ( dup2(pipe_fd[i][dir],def_stdio_fd[i])<0 ) return -1;
				if ( close(pipe_fd[i][dir])!=0 ) return -1;
				if ( close(pipe_fd[i][1-dir])!=0 ) return -1;
			}
			if ( stdio_fd[i]>=0 ) {
				if ( dup2(stdio_fd[i],def_stdio_fd[i])<0 ) return -1;
				if ( close(stdio_fd[i])!=0 ) return -1;
			}
		}
		/* Redirect stderr to stdout */
		if ( err2out && dup2(STDOUT_FILENO,STDERR_FILENO)<0 ) return -1;

		/* Replace child with command */
		execvp(cmd.c_str(), argv.data());
		abort();

	default: /* parent process */

		/* Set and close file descriptors */
		for(int i=0; i<3; i++) {
			if ( stdio_fd[i]==FDREDIR_PIPE ) {
				/* parent process output must connect to the input of
				   the pipe to child, and vice versa for stdout/stderr: */
				const int dir = (i==0 ? PIPE_IN : PIPE_OUT);
				stdio_fd[i] = pipe_fd[i][dir];
				if ( close(pipe_fd[i][1-dir])!=0 ) return -1;
			}
		}

		/* Return if some IO is redirected to be able to read/write to child */
		if ( redirect ) return child_pid;

		/* Wait for the child command to finish */
		int status;
		pid_t pid;
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
}


void version(const char *prog, const char *vers)
{
	std::cout << prog << " -- part of DOMjudge version " << vers << std::endl
	          << "Written by the DOMjudge developers" << std::endl << std::endl
	          << "DOMjudge comes with ABSOLUTELY NO WARRANTY.  This is free software, and you" << std::endl
	          << "are welcome to redistribute it under certain conditions.  See the GNU" << std::endl
	          << "General Public Licence for details." << std::endl;
	exit(0);
}
