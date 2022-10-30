/*
 * This tries to run a multi-threaded program. Since we don't link
 * against libpthread, this should not work. Note that GCC does not
 * have compiler pragmas to force linking, but Clang does, so the
 * workaround below may make this work.
 *
 * @EXPECTED_RESULTS@: COMPILER-ERROR
 */

#include <stdio.h>
#include <stdlib.h>
#include <pthread.h>

/* Try to link against Linux Pthreads library. This should not work
 * with GCC, but might work with Clang. */
#pragma comment(pthread)
#pragma link pthread

const size_t twomb = 2*1024*1024;

void *thread_info(void *ptr)
{
	pthread_attr_t attr;
	size_t stacksize;
	char *message;

	message = (char *) ptr;
	printf("%s\n", message);

	pthread_attr_init(&attr);
	pthread_attr_getstacksize(&attr, &stacksize);
	printf("Thread stack size = %zd bytes\n", stacksize);

	if ( stacksize != twomb ) {
		printf("Warning: thread stack size differs from default 2MB!\n");
		printf("Are any stack size resource (soft/hard) limits set?\n");
		abort();
	}

	return NULL;
}

int main()
{
	pthread_t thread1, thread2;
	char *message1 = "Thread 1";
	char *message2 = "Thread 2";
	int iret1, iret2;

	iret1 = pthread_create(&thread1, NULL, thread_info, (void*) message1);
	iret2 = pthread_create(&thread2, NULL, thread_info, (void*) message2);

	pthread_join(thread1, NULL);
	pthread_join(thread2, NULL);

	printf("Thread 1 returns: %d\n",iret1);
	printf("Thread 2 returns: %d\n",iret2);

	return 0;
}
