/*
   compare -- compare program and testdata output and return diff output.

   $Id$

   This program is a compare wrapper-script for 'test_solution.sh'.
   See that script for syntax and more info. This script is written to
   comply with the ICPC Validator Interface Standard as described in
   http://www.ecs.csus.edu/pc2/doc/valistandard.html.
   
   Usage: compare <testdata.in> <program.out> <testdata.out> <result.xml> <diff.out>

   <testdata.in>   File containing testdata input.
   <program.out>   File containing the program output.
   <testdata.out>  File containing the correct output.
   <result.xml>    File containing an XML document describing the result.
   <diff.out>      File to write program/correct output differences to (optional).

   Exits successfully except when an internal error occurs. Program
   output is considered correct when diff.out is empty (if specified)
   and exitcode is zero.

   Output format of differences:

   - First a line stating from which line differences were found.
   - Then all lines from that line until end of both <program.out> and
     <testdata.out> formatted as
	 '<PROGRAM LINE>' X '<TESTDATA LINE>'
	 The left and right sides are aligned and ending quote (') is
     replaced by an underscore (_) if the line is truncated. The
     middle 'X' is one of the following characters:
	 = both lines are identical
	 ! the lines are different
	 < left contains additional lines not present right
	 > vice versa
     $ only end-of-lines characters differ (e.g. LF vs. CR+LF); note
       that only LF is considered to begin a newline and all CR
       characters are stripped.
	 
 */

#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/wait.h>
#include <sys/types.h>

#include "lib.misc.h"
#include "lib.error.h"

#define min(a,b) ((a) < (b) ? (a) : (b))
#define max(a,b) ((a) > (b) ? (a) : (b))

#define MAXLINELEN 65536

const int maxprintlen = 80;

char *diffoptions = "-U0";

char *progname;

/* filenames of commandline arguments */
char *testin, *testout, *progout, *result, *diffout;

/* Removes end-of-line characters (CR and LF) from string */
char *stripendline(char *str)
{
	size_t i, j;
	
	for ( i = 0, j = 0; str[i] != 0; i++ ) {
		if ( ! (str[i] == '\n' || str[i] == '\r') )
			str[j++] = str[i];
	}
	
	str[j] = 0;

	return str;
}

/* Write an XML result file with result message */
void writeresult(char *msg)
{
	FILE *resultfile;
	
	if ( ! (resultfile = fopen(result, "w")) )
		error(errno, "cannot open '%s'", result);

	fprintf(resultfile, "<?xml version=\"1.0\"?>\n");
	fprintf(resultfile, "<!DOCTYPE result [\n");
	fprintf(resultfile, "  <!ELEMENT result (#PCDATA)>\n");
	fprintf(resultfile, "  <!ATTLIST result outcome CDATA #REQUIRED>\n");
	fprintf(resultfile, "]>\n");
	fprintf(resultfile, "<result outcome=\"%s\">%s</result>\n",msg,msg);

	fclose(resultfile);
}

void writediff(); /* Definition below */

int main(int argc, char **argv)
{
	int redir_fd[3];
	char *cmdargs[MAXARGS];
	pid_t cpid;
	FILE *rpipe, *diffoutfile;
	int status, differror;
	char line[256];

	/* Read arguments. Note that argc counts the number of arguments
	   including the name of the executed program (argv[0]), thus
	   (argc-1) is the real number of arguments. */
	progname = argv[0];
	if ( argc - 1 < 4 )
		error(0, "not enough arguments: %d given, 4 required", argc - 1);
	if ( argc - 1 > 5 )
		error(0, "too many arguments: %d given, max. 5 accepted", argc - 1);
	testin  = argv[1];
	progout = argv[2];
	testout = argv[3];
	result  = argv[4];
	/* Check for optional diff.out filename. */
	if ( (argc - 1) == 5 ) {
		diffout = argv[5];
	} else {
		diffout = NULL;
	}
	
	/* Execute 'diff <diffoptions> progout testout' for exact match of
	   program output. */
	cmdargs[0] = diffoptions;
	cmdargs[1] = progout;
	cmdargs[2] = testout;
	redir_fd[0] = 0;
	redir_fd[1] = 1;
	redir_fd[2] = 0;
	if ( (cpid = execute("diff", cmdargs, 3, redir_fd, 1)) < 0 ) {
		error(errno, "running diff");
	}

	/* Bind diff stdout/stderr to pipe */
	if ( (rpipe = fdopen(redir_fd[1], "r")) == NULL ) {
		error(errno, "opening pipe from diff output");
	}
	
	/* Read stdout/stderr and check for output */
	differror = 0;
	while ( fgets(line, 255, rpipe) != NULL ) {
		if ( strlen(line) > 0 )
			differror = 1;
	}

	if ( fclose(rpipe) != 0 )
		error(errno, "closing pipe from diff output");
	
	if ( waitpid(cpid, &status, 0) < 0 )
		error(errno, "waiting for diff");

	/* Check diff exitcode */
	if ( WIFEXITED(status) && WEXITSTATUS(status) != 0 ) {
		if ( WEXITSTATUS(status) == 1 ) { /* differences were found */
			differror = 1;
		} else { /* any other exitcode!=0 means diff internal error */
			error(0, "diff exited with exitcode %d", WEXITSTATUS(status));
		}
	}

	/* Exit when no diff output found (nothing to do anymore) */
	if ( ! differror ) {
		writeresult("Accepted");
		/* write empty diff.out if requested */
		if ( diffout != NULL ) {
			if ( (diffoutfile = fopen(diffout, "w")) == NULL ) {
				error(errno, "opening file '%s'", diffout);
			}
			fclose(diffoutfile);
		}
		return 0;
	}

	/* Thus we are left with the case of a wrong answer */
	writeresult("Wrong answer");
	
	/* Exit when no 'diffout' file specified (nothing to do anymore) */
	if ( diffout == NULL || strlen(diffout) == 0 )
		return 0;

	writediff();
	
	return 0;
}

/* Writes a readable diff output to 'diffout' */
void writediff()
{
	FILE *diffoutfile;
	FILE *inputfile[2];
	size_t maxlinelen[2], nlines[2];
	char line[2][MAXLINELEN];
	int endoffile[2];
	int i, l;
	int endlinediff, normaldiff;
	char diffchar, quotechar[2];
	char formatstr[256];
	int firstdiff = -1;

	if ( (diffoutfile  = fopen(diffout, "w")) == NULL )
		error(errno, "opening file '%s'", diffout);
	if ( (inputfile[0] = fopen(progout, "r")) == NULL )
		error(errno, "opening file '%s'", progout);
	if ( (inputfile[1] = fopen(testout, "r")) == NULL )
		error(errno, "opening file '%s'", testout);

	/* Find maximum line length and no. lines per input file: */
	for ( i = 0; i < 2; i++ )
		endoffile[i] = maxlinelen[i] = nlines[i] = 0;

	/* Read lines until end of file to find first difference and maxlinelen */
	for ( l = 0; ! (endoffile[0] && endoffile[1]); l++ ) {

		/* Read line of each input file if not already end of file */
		for ( i = 0; i < 2; i++ ) {
			if ( endoffile[i] ) {
				line[i][0] = 0;
				continue;
			}
			if ( fgets(line[i], MAXLINELEN, inputfile[i]) != NULL && strlen(line[i]) != 0 ) {
				nlines[i]++;
			} else {
				endoffile[i] = 1;
				line[i][0] = 0;
			}
		}

		/* Check for differences: _one_ file ended or lines differ */
		if ( firstdiff == -1 &&
		     (endoffile[0] ^ endoffile[1] || strcmp(line[0], line[1]) != 0) )
			firstdiff = l;

		/* Update maxlinelen with length of this line */
		for ( i = 0; i < 2; i++ ) {
			stripendline(line[i]);
			if ( strlen(line[i]) > maxlinelen[i] )
				maxlinelen[i] = strlen(line[i]);
		}
	}

	/* Reset file position to start */
	for( i = 0; i < 2; i++)
		rewind(inputfile[i]);

	/* Determine left/right printing length and construct format
	   string for printf later */
	for( i = 0; i < 2; i++)
		maxlinelen[0] = min(maxlinelen[0], maxprintlen);
	sprintf(formatstr, "%%3d %%c%%-%ds %%c %%c%%s\n", (int)maxlinelen[0] + 1);
	
	/* Print first differences found header at beginning of file */
	fprintf(diffoutfile, "### DIFFERENCES FROM LINE %d ###\n", firstdiff + 1);

	/* Loop over all common lines for printing */
	for( l = 0; l < min(nlines[0], nlines[1]); l++) {

		for( i = 0; i < 2; i++)
			fgets(line[i], MAXLINELEN, inputfile[i]);

		/* Check for endline (or normal) character differences */
		endlinediff = (strcmp(line[0], line[1]) != 0);

		/* Strip endline characters */
		for ( i = 0; i < 2; i++)
			stripendline(line[i]);

		/* Check for just normal character differences */
		normaldiff = (strcmp(line[0],line[1]) != 0);
		
		/* Truncate lines for printing */
		for( i = 0; i < 2; i++) {
			if ( strlen(line[i]) > maxlinelen[i] ) {
				line[i][maxlinelen[i] + 1] = 0;
				line[i][maxlinelen[i]] = '_';
			} else {
				line[i][strlen(line[i]) + 1] = 0;
				line[i][strlen(line[i])] = '\'';
			}
		}

		/* Discern cases '!', '$' and '=' */
		if ( normaldiff ) {
			diffchar = '!';
		} else if ( endlinediff ) {
			diffchar = '$';
		} else {
			diffchar = '=';
		}

		fprintf(diffoutfile, formatstr, l + 1, '\'', line[0], diffchar, '\'', line[1]);
	}

	/* Print lines for single continuing file */
	if ( l < max(nlines[0], nlines[1]) ) {
		if ( nlines[0] > l ) {
			i = 0;
			diffchar = '<';
			quotechar[0] = '\'';
			quotechar[1] = ' ';
		} else {
			i = 1;
			diffchar = '>';
			quotechar[0] = ' ';
			quotechar[1] = '\'';
		}

		for ( ; l < nlines[i]; l++ ) {
			fgets(line[i], MAXLINELEN, inputfile[i]);

			stripendline(line[i]);
			
			if ( strlen(line[i]) > maxlinelen[i] ) {
				line[i][maxlinelen[i] + 1] = 0;
				line[i][maxlinelen[i]] = '_';
			} else {
				line[i][strlen(line[i]) + 1] = 0;
				line[i][strlen(line[i])] = '\'';
			}

			line[1 - i][0] = 0;

			fprintf(diffoutfile, formatstr, l + 1, quotechar[0], line[0],
			                             diffchar, quotechar[1], line[1]);
		}
	}

	/* If no differences found, then some error occurred */
	if ( firstdiff == -1 )
		error(0, "differences reported by 'diff', but none found");

	fclose(diffoutfile);
	fclose(inputfile[0]);
	fclose(inputfile[1]);
}

