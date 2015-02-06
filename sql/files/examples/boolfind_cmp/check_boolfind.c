#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <ctype.h>

FILE *testin, *progout;

int main(int argc, char **argv)
{
	int run, nruns;
	long pos, nbools;
	int *bools;
	char line[256];
	int i, outputvalid;

	if ( argc-1<2 ) {
		fprintf(stderr,"error: not enough arguments: 2 required\n");
		return 1;
	}
	testin  = fopen(argv[1],"r");
	progout = stdin;

	if ( testin==NULL || progout==NULL ) {
		fprintf(stderr,"error: cannot open files\n");
		return 1;
	}

	fscanf(testin,"%d\n",&nruns);

	for(run=1; run<=nruns; run++) {
		fscanf(testin,"%ld\n",&nbools);

		bools = malloc(nbools*sizeof(int));
		if ( bools==NULL ) {
			fprintf(stderr,"error: cannot allocate memory\n");
			return 1;
		}

		for(pos=0; pos<nbools; pos++) fscanf(testin,"%d\n",&bools[pos]);

		if ( fgets(line,255,progout)==NULL ) {
			printf("testcase %d: cannot read line\n",run);
			goto next;
		}

		i = strlen(line)-1;
		while ( i>=0 && line[i]=='\n' ) line[i--] = 0;

		outputvalid = 1;
		if ( strncmp(line,"OUTPUT ",7)!=0 ||
		     strlen(line)<7 || line[7]=='0' ) outputvalid = 0;

		for(i=7; i<strlen(line); i++) {
			if ( !isdigit(line[i]) ) {
				outputvalid = 0;
				break;
			}
		}

		if ( !outputvalid ) {
			printf("testcase %d: invalid output command '%s'\n",run,line);
			goto next;
		}

		sscanf(line,"OUTPUT %ld",&pos);
		if ( pos<0 || pos>=nbools-1 ) {
			printf("testcase %d: position %ld out of range\n",run,pos);
			goto next;
		}

		if ( !bools[pos] || bools[pos+1] ) {
			printf("testcase %d: position %ld,%ld = %s,%s\n",run,pos,pos+1,
			       (bools[pos]   ? "true" : "false"),
			       (bools[pos+1] ? "true" : "false"));
			goto next;
		}

	  next:
		free(bools);
	}

	if ( fgets(line,255,progout)!=NULL ) {
		printf("extra data after last testcase:\n%s",line);
	}

	fclose(testin);
	fclose(progout);

	return 0;
}
