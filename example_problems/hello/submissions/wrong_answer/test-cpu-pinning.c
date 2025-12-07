/*
 * This code forks 10 children and checks that those run on the same
 * CPU core as the parent. Since we're normally doing CPU pinning,
 * this should be true and the program generates a WRONG-ANWSER.
 * If there's a mismatch the program will trigger a RUNTIME-ERROR.
 *
 * @EXPECTED_RESULTS@: WRONG-ANSWER
 */

#define _GNU_SOURCE
#include <errno.h>
#include <unistd.h>
#include <sched.h>
#include <stdio.h>
#include <string.h>
#include <sys/wait.h>

extern int errno;

int main()
{
	unsigned int parent_cpu, cpu;
	getcpu(&parent_cpu, NULL);
	printf("Parent on CPU %d\n", parent_cpu);
	fflush(NULL);

	for(int i=0; i<10; i++) {
		if ( fork()==0 ) {
			// We're in the child
			getcpu(&cpu, NULL);
			printf("Child %d on CPU %d\n", i, cpu);
			fflush(NULL);
			if ( cpu!=parent_cpu ) {
				printf("CPU MISMATCH!\n");
				fflush(NULL);
				return 1;
			}
			return 0;
		}
	}

	// Wait for all our children
	do {
		pid_t pid;
		int status;
		pid = wait(&status);
		if ( pid==-1 ) {
			if ( errno==ECHILD ) {
				printf("Done waiting for all children\n");
			} else {
				printf("Error in wait: %s\n", strerror(errno));
			}
			fflush(NULL);
			break;
		}
		if ( pid>0 ) {
			if ( !WIFEXITED(status) ) {
				printf("Child pid %d terminated abnormally\n", (int)pid);
				fflush(NULL);
				return 1;
			}
			if ( WEXITSTATUS(status)!=0 ) {
				printf("Child pid %d terminated with exit status %d\n",
				       (int)pid, (int)WEXITSTATUS(status));
				return 1;
			}
			printf("Child pid %d terminated\n", (int)pid);
			fflush(NULL);
		}
	} while ( 1 );

	return 0;
}
