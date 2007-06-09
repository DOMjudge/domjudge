/*
   mkstemps -- safely create a temporary filename from a template
   Copyright (C) 2004 Jaap Eldering (eldering@a-eskwadraat.nl).

   Uses the mkstemps function call from the GNU libiberty library.

   $Id$

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2, or (at your option)
   any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software Foundation,
   Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
   
 */

#include <errno.h>
#include <stdlib.h>
#include <unistd.h>
#include <string.h>
#include <stdarg.h>
#include <stdio.h>
#include <getopt.h>

/* Include some functions, which are not always available */
#include "../lib/mkstemps.h"
#include "../lib/basename.h"

#define PROGRAM "mkstemps"
#define VERSION "0.1"
#define AUTHORS "Jaap Eldering"

extern int errno;

const int exit_failure = -1;

const char Xstring[] = "XXXXXX";

char *progname;

int show_help;
int show_version;

struct option const long_opts[] = {
	{"help",    no_argument,       &show_help,    1 },
	{"version", no_argument,       &show_version, 1 },
	{ NULL,     0,                 NULL,          0 }
};

void error(int errnum, const char *format, ...)
{
	va_list ap;
	va_start(ap,format);
	
	fprintf(stderr,"%s",progname);
	
	if ( format!=NULL ) {
		fprintf(stderr,": ");
		vfprintf(stderr,format,ap);
	}
	if ( errnum!=0 ) {
		fprintf(stderr,": %s",strerror(errnum));
	}
	if ( format==NULL && errnum==0 ) {
		fprintf(stderr,": unknown error");
	}
	
	fprintf(stderr,"\nTry `%s --help' for more information.\n",progname);
	va_end(ap);
	
	exit(exit_failure);
}

void version()
{
	printf("%s -- version %s\n",PROGRAM,VERSION);
	printf("Written by %s\n\n",AUTHORS);
	printf("%s comes with ABSOLUTELY NO WARRANTY.  This is free software, and you\n",PROGRAM);
	printf("are welcome to redistribute it under certain conditions.  See the GNU\n");
	printf("General Public Licence for details.\n");
	exit(0);
}

void usage()
{
	printf("Usage: %s [OPTION]... TEMPLATE\n",progname);
	printf("Create a temporary file from TEMPLATE.\n\n");
	printf("      --help          display this help and exit\n");
	printf("      --version       output version information and exit\n");
	printf("\n");
	printf("TEMPLATE should be a filename containing at least 6 consecutive `X'\n");
	printf("characters in the base name. The last 6 of these are replaced by random\n");
	printf("letters and digits such that a unique file is created and returned.\n");
	exit(0);
}

int main(int argc, char **argv)
{
	char *template;
	char *filebase;
	char *ptr;
	char *next;
	int  opt;
	
	progname = argv[0];

	/* Parse command-line options */
	show_help = show_version = 0;
	opterr = 0;
	while ( (opt = getopt_long(argc,argv,"",long_opts,(int *) 0))!=-1 ) {
		switch ( opt ) {
		case 0:   /* long-only option */
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
	if ( show_version ) version();
	
	if ( argc<=optind ) error(0,"no template specified");

	template = argv[optind];

	/* Find the 6 X's in the template */
	filebase = gnu_basename(template);
	
	if ( strstr(filebase,Xstring)==NULL ) {
		error(0,"string %s not found in template",Xstring);
	}

	ptr = strstr(filebase,Xstring);
	while ( (next = strstr(&ptr[1],Xstring))!=NULL ) ptr = next;

	/* Call mkstemps to create the file from template */
	if ( mkstemps(template,strlen(ptr)-strlen(Xstring))<0 ) error(errno,NULL);

	printf("%s\n",template);
	
	return 0;
}
