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
#include <sys/stat.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <getopt.h>
#include <libgen.h>

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

void usage2(int errnum, char *mesg, ...)
{
	va_list ap;
	va_start(ap,mesg);
	
	vlogerror(errnum,mesg,ap);

	va_end(ap);

	printf("Type '%s --help' to get help.\n",progname);
	exit(1);
}

int nwarnings;

void warnuser(char *warning)
{
	nwarnings++;

	logmsg(LOG_DEBUG,"user warning #%d: %s",nwarnings,warning);
	
	if ( ! quiet ) printf("WARNING: %s\n",warning);
}

int socket_fd;
struct sockaddr_in server_addr; // my address information
struct sockaddr_in their_addr;  // connector's address information
int sin_size;

struct stat filestat;

/* Submission information */
string problem, language, server, team;
char *filename;

int main(int argc, char **argv)
{
	unsigned i;
	int c;
	char *ptr;
	string filebase, fileext;
	
	/* Set logging levels & open logfile */
	verbose  = LOG_DEBUG;
	loglevel = LOG_DEBUG;
	stdlog   = fopen(LOGFILE,"a");
	if ( stdlog==NULL ) error(errno,"cannot open logfile `%s'",LOGFILE);

	progname = argv[0];

	nwarnings = 0;

	/* Parse command-line options */
	quiet =	show_help = show_version = 0;
	opterr = 0;
	while ( (c = getopt_long(argc,argv,"p:l:s:t:P:v:q",long_opts,NULL))!=-1 ) {
		switch ( c ) {
		case 0:   /* long-only option */
			break;
		case 'p': /* problem option */
			problem = string(optarg);
			break;
		case 'l': /* language option */
			language = string(optarg);
			break;
		case 's': /* server option */
			server = string(optarg);
			break;
		case 't': /* team option */
			team = string(optarg);
			break;
		case 'P': /* port option */
			port = strtol(optarg,&ptr,10);
			if ( ptr!=0 || port<0 || port>65535 ) {
				usage2(0,"invalid tcp port specified: `%s'",optarg);
			}
			break;
		case 'v': /* verbose option */
			verbose = strtol(optarg,&ptr,10);
			if ( ptr!=0 || verbose<0 ) {
				usage2(0,"invalid verbosity specified: `%s'",optarg);
			}
			break;
		case 'q': /* quiet option */
			verbose = LOG_ERR;
			quiet = 1;
			break;
		case ':': /* getopt error */
		case '?':
			usage2(0,"unknown option or missing argument `%c'",optopt);
			break;
		default:
			error(0,"getopt returned character code `%c' ??",c);
		}
	}

	if ( show_help ) usage();
	if ( show_version ) version();
	
	if ( argc<=optind ) usage2(0,"no filename specified");
	filename = argv[optind];

	/* Stat file and do some sanity checks */
	if ( stat(filename,&filestat)!=0 ) error(errno,"cannot stat `%s'",filename);

	if ( ! S_ISREG(filestat.st_mode) )         warnuser("file is not a regular file");
	if ( ! (filestat.st_mode & S_IRUSR) )      warnuser("file is not readable");
	if ( filestat.st_size==0 )                 warnuser("file is empty");
	if ( filestat.st_size>=(SOURCESIZE*1024) ) warnuser("file is too large");
	
	if ( time(NULL)-filestat.st_mtime>(WARN_MTIME*60) ) {
		warnuser("file has not been modified recently");
	}
	
	/* Try to parse problem and language from filename */
	filebase = string(basename(filename));
	if ( filebase.find('.')!=string::npos ) {
		fileext = filebase.substr(filebase.rfind('.')+1);
		filebase.erase(filebase.find('.'));

		/* Check for only alphanumeric characters */
		for(i=0; i<filebase.length(); i++) {
			if ( ! isalnum(filebase[i]) ) break;
		}
		if ( i>=filebase.length() && filebase.length()>0 ) problem = filebase;

		
	}
	
	if ( problem.empty()  ) usage2(0,"no problem specified");
	if ( language.empty() ) usage2(0,"no language specified");
	if ( team.empty()     ) usage2(0,"no team specified");
	if ( server.empty()   ) usage2(0,"no server specified");

    return 0;
}


//  vim:ts=4:sw=4:et:
