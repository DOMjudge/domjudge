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
   Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

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
#include <netdb.h>
#include <getopt.h>
#include <termios.h>

/* C++ includes for easy string handling */
using namespace std;
#include <iostream>
#include <sstream>
#include <string>
#include <vector>

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
#define PROGRAM "submit"
#define AUTHORS "Peter van de Werken & Jaap Eldering"

#define TIMEOUT 60  /* seconds before send/receive timeouts with an error */
#define LINELEN 256 /* maximum length read from curl stdout lines */

extern int errno;

/* Variables defining logmessages verbosity to stderr/logfile */
extern int verbose;
extern int loglevel;

char *logfile;

char *progname;

int port = SUBMITPORT;

int quiet;
int use_websubmit;
int show_help;
int show_version;

struct option const long_opts[] = {
	{"problem",  required_argument, NULL,         'p'},
	{"language", required_argument, NULL,         'l'},
	{"server",   required_argument, NULL,         's'},
	{"team",     required_argument, NULL,         't'},
	{"port",     required_argument, NULL,         'P'},
	{"web",      optional_argument, NULL,         'w'},
	{"verbose",  optional_argument, NULL,         'v'},
	{"quiet",    no_argument,       NULL,         'q'},
	{"help",     no_argument,       &show_help,    1 },
	{"version",  no_argument,       &show_version, 1 },
	{ NULL,      0,                 NULL,          0 }
};

void version();
void usage();
void usage2(int , char *, ...);
void warnuser(char *);
char readanswer(char *answers);
int  websubmit();
string remove_html_tags(string s);

int nwarnings;

int socket_fd;

/* server name and adress information */
struct sockaddr_in server_sockaddr;
struct in_addr     server_inetaddr;
struct hostent    *serverinfo;

/* Submission information */
string problem, language, extension, server, team;
char *filename, *submitdir, *tempfile;
int temp_fd;

/* Language extensions */
vector<vector<string> > languages;

int main(int argc, char **argv)
{
	unsigned i,j;
	int c;
	int redir_fd[3];
	char *ptr;
	char *args[MAXARGS];
	char *homedir;
	struct stat fstats;
	string filebase, fileext;
	char *lang_exts;
	char *lang, *ext;
	char *lang_ptr, *ext_ptr;
	struct timeval timeout;

	progname = argv[0];
	stdlog = NULL;

	/* Parse LANGEXTS define into separate strings */
	lang_exts = strdup(LANG_EXTS);
	for(lang=strtok_r(lang_exts," ",&lang_ptr); lang!=NULL;
		lang=strtok_r(NULL," ",&lang_ptr)) {

		languages.push_back(vector<string>());
		
		/* First read the language */
		ext=strtok_r(lang,",",&ext_ptr);
		languages[languages.size()-1].push_back(string(ext));
		
		/* Then all valid extensions for that language */
		for(ext=strtok_r(NULL,",",&ext_ptr); ext!=NULL;
			ext=strtok_r(NULL,",",&ext_ptr)) {
			languages[languages.size()-1].push_back(stringtolower(ext));
		}
	}

	if ( getenv("HOME")==NULL ) error(0,"environment variable `HOME' not set");
	homedir = getenv("HOME");
	
	/* Check for USERSUBMITDIR and create it if nessary */
	submitdir = allocstr("%s/%s",homedir,USERSUBMITDIR);
	if ( stat(submitdir,&fstats)!=0 ) {
		if ( mkdir(submitdir,USERPERMDIR)!=0 ) {
			error(errno,"creating directory `%s'",submitdir);
		}
	} else {
		if ( ! S_ISDIR(fstats.st_mode) ) {
			error(0,"`%s' is not a directory",submitdir);
		}
		if ( chmod(submitdir,USERPERMDIR)!=0 ) {
			error(errno,"setting permissions on `%s'",submitdir);
		}
	}
	
	/* Set logging levels & open logfile */
	verbose  = LOG_NOTICE;
	loglevel = LOG_DEBUG;

	logfile = allocstr("%s/submit.log",submitdir);
	stdlog = fopen(logfile,"a");
	if ( stdlog==NULL ) error(errno,"cannot open logfile `%s'",logfile);

	logmsg(LOG_INFO,"started");
	
	/* Set defaults for server and team */
#ifdef SUBMITSERVER
	server = string(SUBMITSERVER);
#endif
	if ( server.empty() && getenv("SUBMITSERVER")!=NULL ) {
		server = string(getenv("SUBMITSERVER"));
	}
	if ( server.empty() ) server = string("localhost");

	if ( team.empty() && getenv("TEAM")!=NULL ) team = string(getenv("TEAM"));
	if ( team.empty() && getenv("USER")!=NULL ) team = string(getenv("USER"));
	if ( team.empty() && getenv("USERNAME")!=NULL ) {
		team = string(getenv("USERNAME"));
	}

	/* Parse command-line options */
	use_websubmit = ! ENABLECMDSUBMIT ;
	quiet =	show_help = show_version = 0;
	opterr = 0;
	while ( (c = getopt_long(argc,argv,"p:l:s:t:P:w::v::q",long_opts,NULL))!=-1 ) {
		switch ( c ) {
		case 0:   /* long-only option */
			break;
			
		case 'p': problem   = string(optarg); break;
		case 'l': extension = string(optarg); break;
		case 's': server    = string(optarg); break;
		case 't': team      = string(optarg); break;
			
		case 'P': /* port option */
			port = strtol(optarg,&ptr,10);
			if ( *ptr!=0 || port<0 || port>65535 ) {
				usage2(0,"invalid tcp port specified: `%s'",optarg);
			}
			break;
		case 'w': /* websubmit option */
			if ( optarg!=NULL ) {
				use_websubmit = strtol(optarg,&ptr,10);
				if ( *ptr!=0 ) usage2(0,"invalid value specified: `%s'",optarg);
			} else {
				use_websubmit = 1;
			}
			break;
		case 'v': /* verbose option */
			if ( optarg!=NULL ) {
				verbose = strtol(optarg,&ptr,10);
				if ( *ptr!=0 || verbose<0 ) {
					usage2(0,"invalid verbosity specified: `%s'",optarg);
				}
			} else {
				verbose++;
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
	
	if ( argc<=optind   ) usage2(0,"no filename specified");
	if ( argc> optind+1 ) usage2(0,"multiple filenames specified");
	filename = argv[optind];

	/* Stat file and do some sanity checks */
	if ( stat(filename,&fstats)!=0 ) usage2(errno,"cannot find `%s'",filename);
	logmsg(LOG_DEBUG,"submission file is %s",filename);

	nwarnings = 0;

	if ( ! (fstats.st_mode & S_IFREG) )    warnuser("file is not a regular file");
	if ( ! (fstats.st_mode & S_IRUSR) )    warnuser("file is not readable");
	if ( fstats.st_size==0 )               warnuser("file is empty");
	if ( fstats.st_size>=SOURCESIZE*1024 ) {
		ptr = allocstr("file is larger than %d kB",SOURCESIZE);
		warnuser(ptr);
		free(ptr);
	}
	
	if ( (i=(time(NULL)-fstats.st_mtime))>WARN_MTIME*60 ) {
		ptr = allocstr("file has not been modified for %d minutes",i/60);
		warnuser(ptr);
		free(ptr);
	}
	
	/* Try to parse problem and language from filename */
	filebase = string(gnu_basename(filename));
	if ( filebase.find('.')!=string::npos ) {
		fileext = filebase.substr(filebase.rfind('.')+1);
		filebase.erase(filebase.find('.'));

		/* Check for only alphanumeric characters in problem */
		for(i=0; i<filebase.length(); i++) {
			if ( ! isalnum(filebase[i]) ) break;
		}
		if ( i>=filebase.length() && filebase.length()>0 && problem.empty() ) {
			problem = filebase;
		}

		if ( extension.empty() ) extension = fileext;
	}
	
	/* Check for languages matching file extension */
	extension = stringtolower(extension);
	for(i=0; i<languages.size(); i++) {
		for(j=1; j<languages[i].size(); j++) {
			if ( languages[i][j]==extension ) {
				language  = languages[i][0];
				extension = languages[i][1];
			}
		}
	}
	
	if ( language.empty() ) {
		ptr = allocstr("language `%s' not recognised",extension.c_str());
		warnuser(ptr);
		free(ptr);
		language = extension;
	}
	
	if ( problem.empty()  ) usage2(0,"no problem specified");
	if ( language.empty() ) usage2(0,"no language specified");
	if ( team.empty()     ) usage2(0,"no team specified");
	if ( server.empty()   ) usage2(0,"no server specified");
	
	logmsg(LOG_DEBUG,"problem is `%s'",problem.c_str());
	logmsg(LOG_DEBUG,"language is `%s'",language.c_str());
	logmsg(LOG_DEBUG,"team is `%s'",team.c_str());
	logmsg(LOG_DEBUG,"server is `%s'",server.c_str());

	/* Ask user for confirmation */
	if ( ! quiet ) {
		printf("Submission information:\n");
		printf("  filename:   %s\n",filename);
		printf("  problem:    %s\n",problem.c_str());
		printf("  language:   %s\n",language.c_str());
		printf("  team:       %s\n",team.c_str());
		printf("  server:     %s\n",server.c_str());
		if ( nwarnings>0 ) printf("There are warnings for this submission!\a\n");
		printf("Do you want to continue? (y/n) ");
		c = readanswer("yn");
		printf("\n");
		if ( c=='n' ) error(0,"submission aborted by user");
	}

	if ( use_websubmit ) return websubmit();
	
	/* Make tempfile to submit */
	tempfile = allocstr("%s/%s.XXXXXX.%s",submitdir,
	                    problem.c_str(),extension.c_str());
	temp_fd = mkstemps(tempfile,extension.length()+1);
	if ( temp_fd<0 || strlen(tempfile)==0 ) {
		error(errno,"mkstemps cannot create tempfile");
	}

	/* Construct copy command and execute it */
	args[0] = filename;
	args[1] = tempfile;
	redir_fd[0] = redir_fd[1] = redir_fd[2] = 0;
	if ( execute(COPY_CMD,args,2,redir_fd,1)!=0 ) {
		error(0,"cannot copy `%s' to `%s'",filename,tempfile);
	}
	
	if ( chmod(tempfile,USERPERMFILE)!=0 ) {
		error(errno,"setting permissions on `%s'",tempfile);
	}

	logmsg(LOG_INFO,"copied `%s' to tempfile `%s'",filename,tempfile);

	/* Connect to the submission server */
	logmsg(LOG_NOTICE,"connecting to the server (%s, %d/tcp)...",
	       server.c_str(),port);
	
	if ( (socket_fd = socket(PF_INET,SOCK_STREAM,0)) == -1 ) {
		error(errno,"cannot open socket");
	}

	/* Set socket timeout option on read/write */
	timeout.tv_sec = TIMEOUT;
	timeout.tv_usec = 0;
	
	if ( setsockopt(socket_fd,SOL_SOCKET,SO_SNDTIMEO,&timeout,sizeof(timeout)) < 0) {
		error(errno,"setting socket option");
	}

	if ( setsockopt(socket_fd,SOL_SOCKET,SO_RCVTIMEO,&timeout,sizeof(timeout)) < 0) {
		error(errno,"setting socket option");
	}

	if ( (serverinfo = gethostbyaddr(server.c_str(),server.length(),AF_INET))==NULL ) {
		if ( (serverinfo = gethostbyname(server.c_str()))==NULL ) {
			error(0,"cannot get address of server");
		}
	}

	server_inetaddr.s_addr = *((unsigned long int *) serverinfo->h_addr_list[0]);
	
	server_sockaddr.sin_family = AF_INET;
	server_sockaddr.sin_port   = htons(port);
	server_sockaddr.sin_addr   = server_inetaddr;
	
	/* Don't bind socket_fd, so a local port automatically is assigned */
	if ( connect(socket_fd,(struct sockaddr *) &server_sockaddr,
	             sizeof(struct sockaddr))!=0 ) {
		error(errno,"cannot connect to the server");
	}

	logmsg(LOG_INFO,"connected, server-address: %s",inet_ntoa(server_inetaddr));

	receive(socket_fd);

	/* Send submission info */
	logmsg(LOG_NOTICE,"sending data...");
	sendit(socket_fd,"+team %s",team.c_str());
	receive(socket_fd);
	sendit(socket_fd,"+problem %s",problem.c_str());
	receive(socket_fd);
	sendit(socket_fd,"+language %s",extension.c_str());
	receive(socket_fd);
	sendit(socket_fd,"+filename %s",gnu_basename(tempfile));
	receive(socket_fd);
	sendit(socket_fd,"+done");

	/* Keep reading until end of file, then check for errors */
	while ( receive(socket_fd) );
	if ( strncasecmp(lastmesg,"done",4)!=0 ) {
		error(0,"connection closed unexpectedly");
	}

	logmsg(LOG_NOTICE,"submission successful");

    return 0;
}

void usage()
{
	unsigned i,j;
	
	printf("Usage: %s [OPTION]... FILENAME\n",progname);
	printf(
"Submit a solution for a problem.\n"
"\n"
"Options (see below for more information)\n"
"  -p, --problem=PROBLEM    submit for problem PROBLEM\n"
"  -l, --language=LANGUAGE  submit in language LANGUAGE\n"
"  -s, --server=SERVER      submit to server SERVER\n"
"  -t, --team=TEAM          submit as team TEAM\n"
#if (ENABLEWEBSUBMIT != 0 && ENABLECMDSUBMIT != 0)
"  -w, --web[=0|1]          submit to the webinterface or toggle; defaults to\n"
"                               no and and should normally not be necessary\n"
#endif
"  -v, --verbose[=LEVEL]    increase verbosity or set to LEVEL, where LEVEL\n"
"                               must be numerically specified as in 'syslog.h'\n"
"                               defaults to LOG_INFO without argument\n"
"  -q, --quiet              set verbosity to LOG_ERR and suppress user\n"
"                               input and warning/info messages\n"
"      --help               display this help and exit\n"
"      --version            output version information and exit\n\n"
"Explanation of submission options:\n"
"\n"
"For PROBLEM use the ID of the problem (letter, number or short name)\n"
"in lower- or uppercase. When not specified, PROBLEM defaults to\n"
"FILENAME excluding the extension.\n"
"For example, 'c.java' will indicate problem 'C'.\n"
"\n"
"For LANGUAGE use one of the following extensions in lower- or uppercase:\n");
	for(i=0; i<languages.size(); i++) {
		printf("   %-10s  %s",(languages[i][0]+':').c_str(),languages[i][1].c_str());
		for(j=2; j<languages[i].size(); j++) printf(", %s",languages[i][j].c_str());
		printf("\n");
	}
	printf(
"The default for LANGUAGE is the extension of FILENAME.\n"
"For example, 'c.java' wil indicate a Java solution.\n"
"\n"
"Examples:\n"
"\n");
	printf("Submit problem 'c' in Java:\n"
	       "    %s c.java\n\n",progname);
	printf("Submit problem 'e' in C++:\n"
	       "    %s --problem e --language=cpp ProblemE.cc\n\n",progname);
	printf("Submit problem 'hello' in C (options override the defaults from FILENAME):\n"
	       "    %s -p hello -l C HelloWorld.java\n\n",progname);
	printf(
"The following options should normally not be needed:\n"
"\n"
"For SERVER use the servername or IP-address of the submit-server.\n"
"The default value for SERVER is defined internally or otherwise\n"
"taken from the environment variable 'SUBMITSERVER', or 'localhost'\n"
"if 'SUBMITSERVER' is not defined.\n"
"\n"
"For TEAM use the login of the account, you want to submit for.\n"
"The default value for TEAM is taken from the environment variable\n"
"'TEAM' or your login name if 'TEAM' is not defined.\n");
	exit(0);
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

void usage2(int errnum, char *mesg, ...)
{
	va_list ap;
	va_start(ap,mesg);
	
	vlogerror(errnum,mesg,ap);

	va_end(ap);

	printf("Type '%s --help' to get help.\n",progname);
	exit(1);
}

void warnuser(char *warning)
{
	nwarnings++;

	logmsg(LOG_DEBUG,"user warning #%d: %s",nwarnings,warning);
	
	if ( ! quiet ) printf("WARNING: %s!\n",warning);
}

char readanswer(char *answers)
{
	struct termios old_termio, new_termio;
	char c;

	/* save the terminal settings for stdin */
	tcgetattr(STDIN_FILENO,&old_termio);
	new_termio = old_termio;

	/* disable canonical mode (buffered i/o) and local echo */
	new_termio.c_lflag &= (~ICANON & ~ECHO);
	tcsetattr(STDIN_FILENO,TCSANOW,&new_termio);

	while ( true ) {
		c = getchar();
		if ( c!=0 && (strchr(answers,tolower(c)) ||
					  strchr(answers,toupper(c))) ) {
			if ( strchr(answers,tolower(c))!=NULL ) {
				c = tolower(c);
			} else {
				c = toupper(c);
			}
			break;
		}
	}

	/* restore the saved settings */
	tcsetattr(STDIN_FILENO,TCSANOW,&old_termio);

	return c;
}

int websubmit()
{
	string cmdline, s;
	char *cmd, *args[MAXARGS], *tmp;
	char line[LINELEN];
	int i, nargs, cpid, status;
	int redir_fd[3];
	FILE *rpipe;

	/* Construct command and execute it */
	cmd = allocstr("curl");
	nargs = 0;
	args[nargs++] = allocstr("-F");
	args[nargs++] = allocstr("code=@%s",filename);
	args[nargs++] = allocstr("-F");
	args[nargs++] = allocstr("probid=%s",problem.c_str());
	args[nargs++] = allocstr("-F");
	args[nargs++] = allocstr("langext=%s",language.c_str());
	args[nargs++] = allocstr("-F");
	args[nargs++] = allocstr("submit=yes");
	args[nargs++] = allocstr(WEBBASEURI "team/upload.php");

	cmdline = string(cmd);
	for(int i=0; i<nargs; i++) cmdline += ' ' + string(args[i]);

	logmsg(LOG_INFO,"websubmit starting '%s'",cmdline.c_str());
	
	redir_fd[0] = 0;
	redir_fd[1] = 1;
	redir_fd[2] = 1;
	
	if ( (cpid = execute(cmd,args,nargs,redir_fd,1))<0 ) {
		error(errno,"starting '%s'",cmdline.c_str());
	}
	
	if ( (rpipe = fdopen(redir_fd[1],"r"))==NULL ) {
		error(errno,"binding stdout to stream");
	}

	/* Read stdout/stderr and find upload status */
	while ( fgets(line,LINELEN,rpipe)!=NULL ) {

		/* Remove newlines from end of line */
		i = strlen(line)-1;
		while ( i>=0 && (line[i]=='\n' || line[i]=='\r') ) line[i--] = 0;

		/* Search line for upload status or errors */
 		if ( (tmp = strstr(line,ERRMATCH))!=NULL ) {
			error(0,"webserver returned: %s",&tmp[strlen(ERRMATCH)]);
 		}
		if ( strstr(line,"uploadstatus")!=NULL ) {
			s = remove_html_tags(string(line));
			if ( s.find("ERROR",0) !=string::npos ||
				 s.find("failed",0)!=string::npos ) {
				error(0,"webserver returned: %s",s.c_str());
			}
			logmsg(LOG_NOTICE,"webserver returned: %s",s.c_str());
		}
		
	}

	if ( fclose(rpipe)!=0 ) error(errno,"closing pipe");
	if ( waitpid(cpid,&status,0)<0 ) error(errno,"waiting for '%s'",cmd);
	
	if ( WIFEXITED(status) && WEXITSTATUS(status)!=0 ) {
		error(0,"'%s' failed with exitcode %d",cmd,WEXITSTATUS(status));
	}

	if ( ! WIFEXITED(status) ) {
		if ( WIFSIGNALED(status) ) {
			error(0,"'%s' terminated with signal %d",cmd,WTERMSIG(status));
		}
		if ( WIFSTOPPED(status) ) {
			error(0,"'%s' stopped with signal %d",cmd,WSTOPSIG(status));
		}
		error(0,"'%s' aborted due to unknown error",cmd);
	}
	if ( status>0 ) error(0,"'%s' exited with exitcode %d",cmd,status);

	free(cmd);
	for(i=0; i<nargs; i++) free(args[i]);

	return 0;
}

string remove_html_tags(string s)
{
	unsigned p1, p2;
	
	while ( (p1=s.find('<',0))!=string::npos ) {
		p2 = s.find('>',p1);
		if ( p2==string::npos ) break;
		s.erase(p1,p2-p1+1);
	}

	return s;
}

//  vim:ts=4:sw=4:
