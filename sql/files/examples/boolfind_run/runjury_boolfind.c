/*
 * Jury program to communicate with contestants' program
 * for the sample "boolfind" interactive problem.
 */

/* Include POSIX.1-2008 base specification */
#define _POSIX_C_SOURCE 200809L

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>
#include <unistd.h>

const struct timespec delay = { 0, 100000 }; /* 0.1 millisec. */

#define maxn 1000000

FILE *in, *out;

int run,  nruns;

long n;
int data[maxn];

void talk()
{
	int nqueries = 0;
	char line[256];
	int i;
	long pos;

	printf("%ld\n",n); fflush(NULL);

	do {
		if ( fgets(line,255,stdin)==NULL ) break;
		for(i=strlen(line)-1; i>=0 && line[i]=='\n'; i--) line[i] = 0;

		if ( strncmp(line,"READ ",5)==0 ) {
			/* We should do a more rigorous syntax check in input
			 * here! E.g. check that nothing follows the number read.
			 */
			if ( sscanf(&line[5],"%ld",&pos)!=1 || pos>=n || pos<0 ) {
				fprintf(out,"invalid READ query '%s' after %d queries\n",line,nqueries);
				break;
			}
			/* Simulate slow query: delay for short while */
			nanosleep(&delay,NULL);
			if ( data[pos] ) {
				printf("true\n");
			} else {
				printf("false\n");
			}
			fflush(NULL);
			nqueries++;
		} else if ( strncmp(line,"OUTPUT ",6)==0 ) {
			fprintf(out,"%s\n",line);
			fprintf(stderr,"#queries = %d\n",nqueries);
			break;
		} else {
			fprintf(out,"unknown command '%s' after %d queries\n",line,nqueries);
			break;
		}
	} while ( 1 );
}

int main(int argc, char **argv)
{
	long i;
	size_t nbuf;
	char buf[256];

	if ( argc-1!=2 ) {
		fprintf(stderr,"error: invalid number of arguments: %d, while 2 expected\n",argc-1);
		exit(1);
	}

	/* Make stdin/stdout unbuffered, just to be sure */
	if ( setvbuf(stdin,  NULL, _IONBF, 0)!=0 ||
	     setvbuf(stdout, NULL, _IONBF, 0)!=0 ) {
		fprintf(stderr,"error: cannot set unbuffered I/O\n");
		exit(1);
	}

	in  = fopen(argv[1],"r");
	out = fopen(argv[2],"w");
	if ( in==NULL || out==NULL ) {
		fprintf(stderr,"error: could not open input and/or output file\n");
		exit(1);
	}

	if ( fscanf(in,"%d\n",&nruns)!=1 ) {
		fprintf(stderr,"error: failed to read number of test cases\n");
		exit(1);
	}
	printf("%d\n",nruns);
	fflush(NULL);

	for(run=1; run<=nruns; run++) {
		if ( fscanf(in,"%ld\n",&n)!=1 ) {
			fprintf(stderr,"error: failed to read data in test case %d\n",run);
			exit(1);
		}

		for(i=0; i<n; i++) {
			if ( fscanf(in,"%d\n",&data[i])!=1 ) {
				fprintf(stderr,"error: failed to read data in test case %d\n",run);
				exit(1);
			}
		}

		talk();
	}

	/* We're done, send EOF */
	fclose(stdout);

	/* Copy any additional data from program */
	while ( (nbuf=fread(buf,1,256,stdin))>0 ) {
		if ( fwrite(buf,1,nbuf,out)!=nbuf ) {
			fprintf(stderr,"error: failed to write additional program data\n");
			exit(1);
		}
	}

	fprintf(stderr,"jury program exited successfully\n");
	return 0;
}
