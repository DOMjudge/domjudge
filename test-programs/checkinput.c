/*
  Program to check testdata input on some general criteria:
  - no leading/trailing whitespace on lines
  - no empty lines
  - newline after each line
  - all whitespace is a single space (so no tabs)
  - lines are no longer than length MAXLINESIZE (incl. newline)
  - only printable characters
  
  Jaap Eldering, 29-03-2004

  $Id$
*/

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <ctype.h>

#define MAXLINESIZE 1048576

char *progname;
FILE *f;

void usage(char *error)
{
  if ( error!=NULL ) printf("Error: %s.\n\n",error);
  
  printf("Usage: %s <file>\n",progname);
  printf("Checks testinput file <file> for anomalies.\n");
}

int check()
{
  char line[MAXLINESIZE+2];
  int linenr,len,pos;
  int error,c;

  error = 0;
  
  for(linenr=1; fgets(line,MAXLINESIZE+2,f)!=NULL; linenr++) {
    len = strlen(line);

    if ( len>MAXLINESIZE ) {
      printf("Line %3d is longer than %d characters.\n",linenr,MAXLINESIZE);
      error = 1;
      if ( line[MAXLINESIZE]!='\n' ) {
	do c=fgetc(f); while ( c!='\n' && c!=EOF );
      }
    }

    if ( len<=1 ) {
      printf("Line %3d is an empty line.\n",linenr);
      error = 1;
    }
    
    if ( line[len-1]!='\n' && len<=MAXLINESIZE ) {
      printf("Line %3d does not end with a newline.\n",linenr);
      error = 1;
    } else {
      len--;
    }

    if ( isspace(line[0]) ) {
      printf("Line %3d starts with whitespace.\n",linenr);
      error = 1;
    }

    if ( isspace(line[len-1]) ) {
      printf("Line %3d ends with whitespace.\n",linenr);
      error = 1;
    }

    for(pos=0; pos<len; pos++) {
      if ( ! isprint(line[pos]) ) {
	printf("Line %3d position %d is a non-printable character.\n",linenr,pos);
	error = 1;
      }
      
      if ( isspace(line[pos]) && line[pos]!=' ' ) {
	printf("Line %3d position %d is non-space whitespace.\n",linenr,pos);
	error = 1;
      }

      if ( pos>0 && isspace(line[pos]) && isspace(line[pos-1]) ) {
	printf("Line %3d position %d is a double whitespace.\n",linenr,pos);
	error = 1;
      }
    }
    
  }

  return !error;
}

int main(int argc, char **argv)
{
  char str[256];
  int ok;
  
  progname = &strrchr(argv[0],'/')[1];

  if ( argc!=2 ) {
    usage(NULL);
    return 1;
  }

  f = fopen(argv[1],"r");
  if ( f==NULL ) {
    sprintf(str,"file '%s' not found",argv[1]);
    usage(str);
    return 1;
  }

  if ( (ok=check()) ) printf("No problems in testdata input format found.\n");
  
  fclose(f);
  
  return !ok;
}
