/*
   check_float -- program to compare output data containing floats.

   Part of the DOMjudge Programming Contest Jury System and licenced
   under the GNU GPL. See README and COPYING for details.


   This program can be used to test solutions to problems where the
   output consists (partially) of floating point numbers. Each line
   will be tokenized by whitespace. First the tokens will be compared
   as strings. If this fails, then the tokens will be parsed as floats
   and compared based on a maximum allowed deviation from the reference
   output and not on exact matching output strings. Each line may
   contain multiple tokens, but these numbers must be equal for both
   files compared.

   Use this program in conjunction with the "compare_program.sh"
   script. In calling this program, two optional options '--abs-prec'
   and '--rel-prec' can be given, which specify the maximum tolerated
   absolute and relative deviation of the teams output from the
   reference output. If the floats are within either bounds, they are
   assumed equal.
 */

#include "config.h"

/* For having access to isfinite() macro in math.h */
#define _ISOC99_SOURCE

#include <stdlib.h>
#include <stdio.h>
#include <stdarg.h>
#include <getopt.h>
#include <string.h>
#include <math.h>
#include <errno.h>
#include <ctype.h>

#define PROGRAM "check_float"
#define VERSION DOMJUDGE_VERSION "/" REVISION

#include "lib.error.h"
#include "lib.misc.h"

#define MAXLINELEN 1024

extern int errno;

/* The floating point type we use internally: */
typedef long double flt;

/* Default absolute and relative precision: */
const flt default_abs_prec = 1E-7;
const flt default_rel_prec = 1E-7;

const char *progname;
const char *file1name, *file2name;
FILE *file1, *file2;

flt abs_prec;
flt rel_prec;
int ignore_ws;

int show_help;
int show_version;

struct option const long_opts[] = {
	{"abs-prec", required_argument, NULL,         'a'},
	{"rel-prec", required_argument, NULL,         'r'},
	{"ignore-ws",no_argument,       NULL,         'w'},
	{"help",     no_argument,       &show_help,    1 },
	{"version",  no_argument,       &show_version, 1 },
	{ NULL,      0,                 NULL,          0 }
};

void usage()
{
	printf("Usage: %s [OPTION]... <IGNORED> <FILE1> <FILE2>\n",progname);
	printf("Compare program output in file <FILE1> with reference output in\n");
	printf("file <FILE2> for floating point numbers with finite precision.\n");
	printf("When one <FILE> is given as `-', it is read from standard input.\n");
	printf("The first argument <IGNORED> is ignored, but needed for compatibility.\n");
	printf("\n");
	printf("  -a, --abs-prec=PREC  use PREC as relative precision (default: 1E-7)\n");
	printf("  -r, --rel-prec=PREC  use PREC as absolute precision (default: 1E-7)\n");
	printf("  -w, --ignore-ws      ignore whitespace differences\n");
	printf("      --help           display this help and exit\n");
	printf("      --version        output version information and exit\n");
	printf("\n");
	exit(0);
}

/* Test two numbers for equality, accounting for +/-INF, NaN and precision */
int equal(flt f1, flt f2)
{
	flt absdiff, reldiff;

	/* Finite values are compared with some tolerance */
	if ( isfinite(f1) && isfinite(f2) ) {
		absdiff = fabsl(f1-f2);
		reldiff = fabsl((f1-f2)/f2);
		return !(absdiff > abs_prec && reldiff > rel_prec);
	}

	/* NaN is equal to NaN */
	if ( isnan(f1) && isnan(f2) ) return 1;

	/* Infinite values are equal if their sign matches */
	if ( isinf(f1) && isinf(f2) ) {
		return (f1 < 0 && f2 < 0) || (f1 > 0 && f2 > 0);
	}

	/* Values in different classes are always different. */
	return 0;
}

/* Read whitespace from str into res, returns number of characters read */
int scanspace(const char *str, char *res)
{
	int pos = 0;
	while ( isspace(str[pos]) ) {
		res[pos] = str[pos];
		pos++;
	}

	res[pos] = 0;
	return pos;
}

int main(int argc, char **argv)
{
	int opt;
	char *ptr;
	int linenr, tokennr, diff, wsdiff;
	char line1[MAXLINELEN], line2[MAXLINELEN];
	char *ptr1, *ptr2;
	int pos1, pos2;
	int read1, read2, n1, n2;
	char str1[MAXLINELEN], str2[MAXLINELEN];
	flt f1, f2;
	flt absdiff, reldiff;

	progname = argv[0];

	/* Parse command-line options */
	abs_prec = default_abs_prec;
	rel_prec = default_rel_prec;
	ignore_ws = 0;
	show_help = show_version = 0;
	opterr = 0;
	while ( (opt = getopt_long(argc,argv,"a:r:w",long_opts,(int *) 0))!=-1 ) {
		switch ( opt ) {
		case 0:   /* long-only option */
			break;
		case 'a': /* absolute precision */
			abs_prec = strtold(optarg,&ptr);
			if ( *ptr!=0 || ptr==(char *)&optarg )
				error(errno,"incorrect absolute precision specified");
			break;
		case 'r': /* relative precision */
			rel_prec = strtold(optarg,&ptr);
			if ( *ptr!=0 || ptr==(char *)&optarg )
				error(errno,"incorrect relative precision specified");
			break;
		case 'w': /* ignore whitespace errors */
			ignore_ws = 1;
			break;
		case ':': /* getopt error */
		case '?':
			error(0,"unknown option or missing argument `%c'",optopt);
			break;
		default:
			error(0,"getopt returned character code `%c' ??",(char)opt);
		}
	}
	if ( show_help ) usage();
	if ( show_version ) version(PROGRAM,VERSION);

	if ( argc<optind+3 ) error(0,"not enough arguments given");

	file1name = argv[optind+1];
	file2name = argv[optind+2];

	if ( strcmp(file1name,"-")==0 && strcmp(file2name,"-")==0 ) {
		error(0,"both files specified as standard input");
	}

	if ( strcmp(file1name,"-")==0 ) {
		file1 = stdin;
	} else {
		if ( (file1 = fopen(file1name,"r"))==NULL ) error(errno,"cannot open '%s'",file1name);
	}
	if ( strcmp(file2name,"-")==0 ) {
		file2 = stdin;
	} else {
		if ( (file2 = fopen(file2name,"r"))==NULL ) error(errno,"cannot open '%s'",file2name);
	}

	linenr = 0;
	diff = 0;
	wsdiff = 0;

	while ( 1 ) {
		linenr++;
		ptr1 = fgets(line1,MAXLINELEN,file1);
		ptr2 = fgets(line2,MAXLINELEN,file2);

		if ( ptr1==NULL && ptr2==NULL ) break;

		if ( ptr1==NULL && ptr2!=NULL ) {
			printf("line %3d: file 1 ended before 2.\n",linenr);
			diff++;
			break;
		}
		if ( ptr1!=NULL && ptr2==NULL ) {
			printf("line %3d: file 2 ended before 1.\n",linenr);
			diff++;
			break;
		}

		/* Check leading whitespace */
		n1 = scanspace(line1,str1);
		n2 = scanspace(line2,str2);
		if ( strcmp(str1,str2)!=0 ) {
			wsdiff++;
			if ( !ignore_ws ) {
				printf("line %3d: whitespace mismatch at begin of line.\n",linenr);
			}
		}

		tokennr = 0;
		pos1 = n1;
		pos2 = n2;
		while ( 1 ) {
			tokennr++;

			read1 = sscanf(&line1[pos1],"%s",str1);
			read2 = sscanf(&line2[pos2],"%s",str2);
			sscanf(&line1[pos1],"%*s%n",&n1);
			sscanf(&line2[pos2],"%*s%n",&n2);
			pos1 += n1;
			pos2 += n2;

			if ( read1!=1 && read2==1 ) {
				printf("line %3d: file 1 misses %d-th token.\n",linenr,tokennr);
				diff++;
				break;
			}
			if ( read1==1 && read2!=1 ) {
				printf("line %3d: file 1 has excess %d-th token.\n",linenr,tokennr);
				diff++;
				break;
			}

			/* Check if tokens are equal as strings */
			if ( strcmp(str1,str2)==0 ) goto tokendone;

			read1 = sscanf(str1,"%Lf",&f1);
			read2 = sscanf(str2,"%Lf",&f2);

			if ( read1==0 ) {
				printf("line %3d: file 1, %d-th entry cannot be parsed as float.\n",
				       linenr,tokennr);
				diff++;
				break;
			}
			if ( read2==0 ) {
				printf("line %3d: file 2, %d-th entry cannot be parsed as float.\n",
				       linenr,tokennr);
				diff++;
				break;
			}

			if ( !(read1==1 && read2==1) ) {
				printf("line %3d: %d-th non-float tokens differ: '%s' != '%s'.\n",
				       linenr,tokennr,str1,str2);
				diff++;
			}

			if ( ! equal(f1,f2) ) {
				diff++;
				printf("line %3d: %d-th float differs: %8LG != %-8LG",
				       linenr,tokennr,f1,f2);
				if ( isfinite(f1) && isfinite(f2) ) {
					absdiff = fabsl(f1-f2);
					reldiff = fabsl((f1-f2)/f2);
					if ( absdiff>abs_prec ) printf("  absdiff = %9.5LE",absdiff);
					if ( reldiff>rel_prec ) printf("  reldiff = %9.5LE",reldiff);
				}
				printf("\n");
			}

		  tokendone:
			/* Check whitespace after tokens */
			n1 = scanspace(&line1[pos1],str1);
			n2 = scanspace(&line2[pos2],str2);
			if ( strcmp(str1,str2)!=0 ) {
				wsdiff++;
				if ( !ignore_ws ) {
					printf("line %3d: whitespace mismatch after %d-th token.\n",
					       linenr,tokennr);
				}
			}
			pos1 += n1;
			pos2 += n2;

			/* No more tokens on this line */
			if ( line1[pos1]==0 && line2[pos2]==0 ) break;
		}
	}

	fclose(file1);
	fclose(file2);

	if ( diff > 0 ) printf("Found %d differences in %d lines\n",diff,linenr-1);
	if ( !ignore_ws && wsdiff > 0 ) {
		printf("Found %d whitespace differences in %d lines\n",wsdiff,linenr-1);
	}

	return 0;
}
