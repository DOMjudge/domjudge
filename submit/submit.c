/*
   submit -- command-line submit program for solutions.
   Copyright (C) 2004 Peter van de Werken, Jaap Eldering.

   Based on submit.pl by Eelco Dolstra.
   
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

#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <errno.h>
#include <syslog.h>
#include <string.h>
#include <sys/wait.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <getopt.h>

/* Some C++ includes for easy string handling */
using namespace std;
#include <iostream>
#include <sstream>
#include <string>

/* Some system/site specific config */
#include "../etc/config.h"

/* Logging and error functions */
#include "../lib/lib.error.h"

/* Common send/receive functions */
#include "submitcommon.h"

#define PROGRAM "submitdaemon"
#define VERSION "0.1"
#define AUTHORS "Peter van de Werken & Jaap Eldering"

#define BACKLOG 32      // how many pending connections queue will hold

extern int errno;

/* Variables defining logmessages verbosity to stderr/logfile */
#define LOGFILE LOGDIR"/submit.log"

extern int  verbose;
extern int  loglevel;
extern char *logfile;

char *progname;

int port = SUBMITPORT;

int quiet;
int show_help;
int show_version;

struct option const long_opts[] = {
	{"problem",  required_argument, NULL,         'p'},
	{"language", required_argument, NULL,         'l'},
	{"server",   required_argument, NULL,         's'},
	{"team",     required_argument, NULL,         't'},
	{"port",     required_argument, NULL,         'P'},
	{"verbose",  required_argument, NULL,         'v'},
	{"quiet",    no_argument,       NULL,         'q'},
	{"help",     no_argument,       &show_help,    1 },
	{"version",  no_argument,       &show_version, 1 },
	{ NULL,      0,                 NULL,          0 }
};

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
	printf("Usage: %s [OPTION]... FILENAME\n",progname);
	
	printf("\
Submit a solution for a problem.

Options (see below for more information)
  -p, --problem=PROBLEM    submit for problem PROBLEM
  -l, --language=LANGUAGE  submit in language LANGUAGE
  -s, --server=SERVER      submit to server SERVER
  -t, --team=TEAM          submit as team TEAM
  -v, --verbose=LEVEL      set verbosity to LEVEL, where LEVEL must be
                               numerically specified as in 'syslog.h'
                               defaults to LOG_INFO without argument
  -q, --quiet              set verbosity to LOG_ERR and suppress user
                               input and warning/info messages
      --help               display this help and exit
      --version            output version information and exit

Explanation of submission options:

For PROBLEM use the ID of the problem (letter, number or short name)
in lower- or uppercase. When not specified, PROBLEM defaults to
FILENAME excluding the extension.
For example, 'c.java' will indicate problem 'C'.

For LANGUAGE use one of the following in lower- or uppercase:
   C:        c
   C++:      cc, cpp, c++
   Java:     java
   Pascal:   pas
The default for LANGUAGE is the extension of FILENAME.
For example, 'c.java' wil indicate a Java solution.

Examples:
");

	printf("Submit problem 'c' in Java:\n\t%s c.java\n\n",progname);
	printf("Submit problem 'e' in C++:\n"
		   "\t%s --problem e --language=cpp ProblemE.cc\n\n",progname);
	printf("Submit problem 'hello' in C (options override the defaults from FILENAME):\n"
		   "\t%s -p hello -l C HelloWorld.java\n\n",progname);
	
	printf("\

The following options should normally not be needed:

For SERVER use the servername or IP-address of the submit-server.
The default value for SERVER is defined internally or otherwise
taken from the environment variable 'SUBMITSERVER', or 'localhost'
if 'SUBMITSERVER' is not defined.

For TEAM use the login of the account, you want to submit for.
The default value for TEAM is taken from the environment variable
'TEAM' or your login name if 'TEAM' is not defined.
");
	
	exit(0);
}

int socket_fd;
struct sockaddr_in server_addr; // my address information
struct sockaddr_in their_addr;  // connector's address information
int sin_size;

/* Submission information */
string problem, language, server, team, filename;

int main(int argc, char **argv)
{
	int c;
	char *ptr;
	
	/* Set logging levels & open logfile */
	verbose  = LOG_DEBUG;
	loglevel = LOG_DEBUG;
	stdlog   = fopen(LOGFILE,"a");
	if ( stdlog==NULL ) error(errno,"cannot open logfile `%s'",LOGFILE);

	progname = argv[0];

	/* Parse command-line options */
	quiet =	show_help = show_version = 0;
	opterr = 0;
	while ( (c = getopt_long(argc,argv,"p:l:s:t:P:v:q",long_opts,NULL))!=-1 ) {
		switch ( c ) {
		case 0:   /* long-only option */
			break;
		case 'p': /* problem option */
			break;
		case 'l': /* language option */
			break;
		case 's': /* server option */
			break;
		case 't': /* team option */
			break;
		case 'P': /* port option */
			port = strtol(optarg,&ptr,10);
			if ( ptr!=0 || port<0 || port>65535 ) {
				error(0,"invalid tcp port specified: `%s'",optarg);
			}
			break;
		case 'v': /* verbose option */
			verbose = strtol(optarg,&ptr,10);
			if ( ptr!=0 || verbose<0 ) {
				error(0,"invalid verbosity specified: `%s'",optarg);
			}
			break;
		case 'q': /* quiet option */
			verbose = LOG_ERR;
			quiet = 1;
			break;
		case ':': /* getopt error */
		case '?':
			error(0,"unknown option or missing argument `%c'",optopt);
			break;
		default:
			error(0,"getopt returned character code `%c' ??",c);
		}
	}

	if ( show_help ) usage();
	if ( show_version ) version();
	
	if ( argc<=optind ) error(0,"no filename specified");

    return 0;
}


//  vim:ts=4:sw=4:et:
