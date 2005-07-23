/*
   submitdaemon -- server for the submit program.
   Copyright (C) 2004 Peter van de Werken, Jaap Eldering.

   Based on submitdaemon.pl by Eelco Dolstra.
   
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
#include <signal.h>
#include <getopt.h>
#include <pwd.h>
#include <libgen.h>

/* C++ includes for easy string handling */
using namespace std;
#include <iostream>
#include <sstream>
#include <string>

/* System/site specific config */
#include "../etc/config.h"

/* Logging and error functions */
#include "../lib/lib.error.h"

/* Include some functions, which are not always available */
#include "../lib/mkstemps.h"
#include "../lib/basename.h"

/* Common send/receive functions */
#include "submitcommon.h"

/* These defines are needed in 'version' */
#define DOMJUDGE_PROGRAM "DOMjudge/" DOMJUDGE_VERSION
#define PROGRAM "submitdaemon"
#define AUTHORS "Peter van de Werken & Jaap Eldering"

#define BACKLOG 32      /* how many pending connections queue will hold */
#define LINELEN 256     /* maximum length read from submit_db stdout lines */

extern int errno;

/* Variables defining logmessages verbosity to stderr/logfile */
#define LOGFILE LOGDIR"/submit.log"

extern int verbose;
extern int loglevel;

char *progname;

int port = SUBMITPORT;

int show_help;
int show_version;

struct option const long_opts[] = {
	{"port",    required_argument, NULL,         'P'},
	{"verbose", required_argument, NULL,         'v'},
	{"help",    no_argument,       &show_help,    1 },
	{"version", no_argument,       &show_version, 1 },
	{ NULL,     0,                 NULL,          0 }
};

int server_fd, client_fd;       /* server/client socket filedescriptors */
struct sockaddr_in server_addr; /* server address information */
struct sockaddr_in client_addr; /* client address information */
socklen_t sin_size;

void version();
void usage();
void create_server();
int  handle_client();
void sigchld_handler(int);

int main(int argc, char **argv)
{
	struct sigaction sigchildaction;
	int child_pid;
	int c;
	char *ptr;
	
	progname = argv[0];

	/* Set logging levels & open logfile */
	verbose  = LOG_DEBUG;
	loglevel = LOG_DEBUG;
	stdlog   = fopen(LOGFILE,"a");
	if ( stdlog==NULL ) error(errno,"cannot open logfile `%s'",LOGFILE);

	/* Parse command-line options */
	show_help = show_version = 0;
	opterr = 0;
	while ( (c = getopt_long(argc,argv,"P:v:q",long_opts,NULL))!=-1 ) {
		switch ( c ) {
		case 0:   /* long-only option */
			break;
		case 'P': /* port option */
			port = strtol(optarg,&ptr,10);
			if ( *ptr!=0 || port<0 || port>65535 ) {
				error(0,"invalid TCP port specified: `%s'",optarg);
			}
			break;
		case 'v': /* verbose option */
			verbose = strtol(optarg,&ptr,10);
			if ( *ptr!=0 || verbose<0 ) {
				error(0,"invalid verbosity specified: `%s'",optarg);
			}
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
	
	if ( argc>optind ) error(0,"non-option arguments given");

	logmsg(LOG_NOTICE,"server started [%s]", DOMJUDGE_PROGRAM);

	create_server();
	logmsg(LOG_INFO,"listening on port %d/tcp", port);
    
    /* Setup the child signal handler */
	sigchildaction.sa_handler = sigchld_handler;
	sigemptyset(&sigchildaction.sa_mask);
	sigchildaction.sa_flags = SA_RESTART;
	if ( sigaction(SIGCHLD,&sigchildaction,NULL)!=0 ) {
		error(errno,"setting child signal handler");
	}
	logmsg(LOG_DEBUG,"child signal handler installed");
	
    /* main accept() loop */
    while ( true ) {
        sin_size = sizeof(struct sockaddr_in);
		
		if ( (client_fd = accept(server_fd, (struct sockaddr *) &client_addr,
		                         &sin_size)) == -1 ) {
			warning(errno,"accepting incoming connection");
			continue;
		}
        
		logmsg(LOG_INFO,"incoming connection, spawning child");
        
		switch ( child_pid = fork() ) {
		case -1: /* error */
			error(errno,"cannot fork");
		
		case  0: /* child thread */
			close(server_fd); /* child doesn't need the listener */
			signal(SIGCHLD,SIG_DFL); /* child should not listen to signals */
			
			logmsg(LOG_NOTICE,"connection from %s",inet_ntoa(client_addr.sin_addr));

			handle_client();

			logmsg(LOG_INFO,"child exiting");
			exit(0);

		default: /* parent thread */
			close(client_fd); /* parent doesn't need the client_fd */
			logmsg(LOG_DEBUG,"spawned child, pid=%d", child_pid);
		}

	}

	return FAILURE; /* This should never be reached */
}

void version()
{
	printf("%s %s\nWritten by %s\n\n",DOMJUDGE_PROGRAM,PROGRAM,AUTHORS);
	printf(
"%s comes with ABSOLUTELY NO WARRANTY.  This is free software, and you\n"
"are welcome to redistribute it under certain conditions.  See the GNU\n"
"General Public Licence for details.\n",PROGRAM);
	exit(0);
}

void usage()
{
	printf(
"Usage: %s [OPTION]...\n"
"Start the submitserver.\n"
"\n"
"  -P, --port=PORT       set TCP port to listen on to PORT (default: %i)\n"
"  -v, --verbose=LEVEL   set verbosity to LEVEL (syslog levels)\n"
"      --help            display this help and exit\n"
"      --version         output version information and exit\n"
"\n",progname,port);
	
	exit(0);
}

/***
 *  Open a listening socket on the localhost.
 */
void create_server()
{
	/* setsockopt needs a pointer to the value of the option to be set */
	int enable = 1;
	
	if ( (server_fd = socket(PF_INET,SOCK_STREAM,0)) == -1 ) {
		error(errno,"cannot open server socket");
	}

	if ( setsockopt(server_fd,SOL_SOCKET,SO_REUSEADDR,&enable,sizeof(int))!=0 ) {
		error(errno,"cannot set socket options");
	}

	server_addr.sin_family      = AF_INET;      /* address family                */
	server_addr.sin_port        = htons(port);  /* port in network shortorder    */
	server_addr.sin_addr.s_addr = INADDR_ANY;   /* automatically fill with my IP */

	if ( bind(server_fd,(struct sockaddr *) &server_addr,
	          sizeof(struct sockaddr))!=0 ) {
		error(errno,"binding server socket");
	}

	if ( listen(server_fd,BACKLOG)!=0 ) {
		error(errno,"starting listening");
	}
}

/***
 *  Talk with a client: receive submission information and copy source-file.
 */
int handle_client()
{
	string command, argument;
	string team, problem, language, filename;
	char *fromfile, *tempfile, *tmp, *tmp2;
	struct passwd *userinfo;
	char *args[MAXARGS];
	int redir_fd[3];
	int status;
	pid_t cpid;
	FILE *rpipe;
	char line[LINELEN];
	int i;
	
	sendit(client_fd,"+server ready");

	while ( receive(client_fd) ) {

		// Make sure that tmp is big enough to contain command and argument
		tmp2 = tmp = allocstr("%s",lastmesg);
		
		strsep(&tmp2," ");
		
		command.erase();
		argument.erase();
		
		if ( tmp !=NULL ) command  = string(tmp);
		if ( tmp2!=NULL ) argument = string(tmp2);
		
		free(tmp);
	
		command = stringtolower(command);
		if ( command=="team" ) {
			team = argument;
			sendit(client_fd,"+received team '%s'",argument.c_str());
		} else
		if ( command=="problem" ) {
			problem = stringtolower(argument);
			sendit(client_fd,"+received problem '%s'",argument.c_str());
		} else
		if ( command=="language" ) {
			language = stringtolower(argument);
			sendit(client_fd,"+received language '%s'",argument.c_str());
		} else
		if ( command=="filename" ) {
			filename = argument;
			sendit(client_fd,"+received filename '%s'",argument.c_str());
		} else 
		if ( command=="quit" ) {
			logmsg(LOG_NOTICE,"received quit, aborting");
			close(client_fd);
			return FAILURE;
		} else 
		if ( command=="done" ) {
			break;
		} else {
			senderror(client_fd,0,"invalid command: '%s'",command.c_str());
		}
	}
	
	if ( problem.empty()  || team.empty() ||
	     language.empty() || filename.empty() ) {
		senderror(client_fd,0,"missing submission info");
	}

	logmsg(LOG_NOTICE,"submission received: %s/%s/%s",
	       team.c_str(),problem.c_str(),language.c_str());

	/* Create the absolute path to submission file, which is expected
	   (and for security explicitly taken) to be basename only! */
	if ( (userinfo = getpwnam(team.c_str()))==NULL ) {
		senderror(client_fd,0,"cannot find team username");
	}

	fromfile = allocstr("%s/%s/%s",userinfo->pw_dir,USERSUBMITDIR,
	                    gnu_basename(filename.c_str()));
	
	tempfile = allocstr("%s/%s.%s.XXXXXX.%s",INCOMINGDIR,
	                    problem.c_str(),team.c_str(),language.c_str());
	
	if ( mkstemps(tempfile,language.length()+1)<0 || strlen(tempfile)==0 ) {
		senderror(client_fd,errno,"mkstemps cannot create tempfile");
	}
	
	logmsg(LOG_INFO,"created tempfile: `%s'",gnu_basename(tempfile));
	
	/* Copy the source-file */
	args[0] = (char *) team.c_str();
	args[1] = fromfile;
	args[2] = tempfile;
	redir_fd[0] = redir_fd[1] = redir_fd[2] = 0;
	switch ( (status = execute("./submit_copy.sh",args,3,redir_fd,1)) ) {
	case  0: break;
	case -1: senderror(client_fd,errno,"starting submit_copy");
	case -2: senderror(client_fd,0,"starting submit_copy: internal error");
	default: senderror(client_fd,0,"submit_copy failed with exitcode %d",status);
	}
	
	logmsg(LOG_INFO,"copied `%s' to tempfile",filename.c_str());
	
	/* Check with database for correct parameters
	   and then add a database entry for this file. */
	args[0] = (char *) team.c_str();
	args[1] = inet_ntoa(client_addr.sin_addr);
	args[2] = (char *) problem.c_str();
	args[3] = (char *) language.c_str();
	args[4] = gnu_basename(tempfile);
	redir_fd[0] = 0;
	redir_fd[1] = 1;
	redir_fd[2] = 0;
	if ( (cpid = execute("./submit_db.php",args,5,redir_fd,1))<0 ) {
		senderror(client_fd,errno,"starting submit_db");
	}

	if ( (rpipe = fdopen(redir_fd[1],"r"))==NULL ) {
		senderror(client_fd,errno,"binding submit_db stdout to stream");
	}
	
	/* Read stdout/stderr and try to find errors */
	while ( fgets(line,LINELEN,rpipe)!=NULL ) {

		/* Remove newlines from end of line */
		i = strlen(line)-1;
		while ( i>=0 && (line[i]=='\n' || line[i]=='\r') ) line[i--] = 0;

		fprintf(stderr,"%s\n",line);
		
		/* Search line for error/warning messages */
		if ( (tmp = strstr(line,ERRMATCH))!=NULL ) {
			senderror(client_fd,0,"%s",&tmp[strlen(ERRMATCH)]);
		}
		if ( (tmp = strstr(line,WARNMATCH))!=NULL ) {
			sendwarning(client_fd,0,"%s",&tmp[strlen(WARNMATCH)]);
			return WARNING;
		}
	}

	if ( fclose(rpipe)!=0 ) {
		senderror(client_fd,errno,"closing submit_db pipe");
	}
	
	if ( waitpid(cpid,&status,0)<0 ) {
		senderror(client_fd,errno,"waiting for submit_db");
	}
	
	if ( WIFEXITED(status) && WEXITSTATUS(status)!=0 ) {
		senderror(client_fd,0,"submit_db failed with exitcode %d",
		          WEXITSTATUS(status));
	}

	if ( ! WIFEXITED(status) ) {
		if ( WIFSIGNALED(status) ) {
			senderror(client_fd,0,"submit_db terminated with signal %d",
					  WTERMSIG(status));
		}
		if ( WIFSTOPPED(status) ) {
			senderror(client_fd,0,"submit_db stopped with signal %d",
					  WSTOPSIG(status));
		}
		senderror(client_fd,0,"submit_db aborted due to unknown error");
	}
	
	logmsg(LOG_INFO,"added submission to database");
	
	if ( unlink(tempfile)!=0 ) error(errno,"deleting tempfile");

	sendit(client_fd,"+done submission successful");
	close(client_fd);
	
	return SUCCESS;
}

/***
 *  Watch and report termination of child threads
 */
void sigchld_handler(int sig)
{
	pid_t pid;
	int exitcode;
	
	pid = waitpid(0,&exitcode,WNOHANG);

	if ( pid<=0 ) {
		/* Don't react on "No child processes" or looping will occur
		   due to childs from 'system' call */
		if ( errno==ECHILD ) return;
		warning(errno,"waiting for child, pid = %d, exitcode = %d",pid,exitcode);
		system(SYSTEM_ROOT"/bin/beep "BEEP_ERROR" &");
		return;
	}
	
	logmsg(LOG_INFO,"child process %d exited with exitcode %d",pid,exitcode);

	/* Audibly report submission status with beeps */
	switch ( exitcode ) {
	case SUCCESS:
		system(BEEP_CMD" "BEEP_SUBMIT" &");
		break;

	case WARNING:
		system(BEEP_CMD" "BEEP_WARNING" &");
		break;

	case FAILURE:
	default:
		system(BEEP_CMD" "BEEP_ERROR" &");
	}
}

//  vim:ts=4:sw=4:
