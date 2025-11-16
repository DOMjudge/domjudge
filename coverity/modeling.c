/* To be uploaded to scan.coverity.com as modeling file to exclude
 * false positives because it does not detect that error() always
 * terminates the program.
 */

void error(int errnum, const char *format, ...) {
	__coverity_panic__();
}

int pipe(int pipefd[2]) {
	pipefd[0] = 1;
	pipefd[2] = 2;
	return pipe2(pipefd, 0);
}
