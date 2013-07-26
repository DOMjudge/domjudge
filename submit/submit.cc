/*
 * submit -- command-line submit program for solutions.
 *
 * Based on submit.pl by Eelco Dolstra.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 *
 */

#include "config.h"

#include "submit-config.h"

/* Check whether default submission method is available; bail out if not */
#if ( SUBMIT_DEFAULT == 1 ) && ( SUBMIT_ENABLE_CMD != 1 )
#error "Commandline default submission requested, but server not enabled."
#endif
#if ( SUBMIT_DEFAULT == 2 ) && ( SUBMIT_ENABLE_WEB != 1 )
#error "Webinterface default submission requested, but server not enabled."
#endif
#if ( SUBMIT_DEFAULT < 1 ) || ( SUBMIT_DEFAULT > 2 )
#error "Unknown submission method requested."
#endif
/* Check whether submission method dependencies are available */
#if ( SUBMIT_ENABLE_CMD && ! ( HAVE_NETDB_H && HAVE_NETINET_IN_H ) )
#error "Commandline submission requested, but network headers not available."
#endif
#if ( SUBMIT_ENABLE_WEB && ! HAVE_CURL_CURL_H )
#error "Webinterface submission requested, but libcURL not available."
#endif

/* Standard include headers */
#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <errno.h>
#include <string.h>
#include <sys/wait.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/socket.h>
#include <getopt.h>
#include <termios.h>
#if ( SUBMIT_ENABLE_CMD )
#include <netinet/in.h>
#include <netdb.h>
#endif
#ifdef HAVE_CURL_CURL_H
#include <curl/curl.h>
#include <curl/easy.h>
#endif
#ifdef HAVE_MAGIC_H
#include <magic.h>
#endif

#include <jsoncpp/json/json.h>

/* C++ includes for easy string handling */
#include <iostream>
#include <sstream>
#include <string>
#include <vector>
using namespace std;

/* These defines are needed in 'version' and 'logmsg' */
#define DOMJUDGE_PROGRAM "DOMjudge/" DOMJUDGE_VERSION
#define PROGRAM "submit"
#define VERSION DOMJUDGE_VERSION "/" REVISION

/* Logging and error functions */
#include "lib.error.h"

/* Misc. other functions */
#include "lib.misc.h"

/* Include some functions, which are not always available */
#include "mkstemps.h"
#include "basename.h"

/* Common send/receive functions */
#include "submitcommon.hxx"

const int timeout_secs = 60; /* seconds before send/receive timeouts with an error */

/* Variables defining logmessages verbosity to stderr/logfile */
extern int verbose;
extern int loglevel;

char *logfile;

const char *progname;

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
	{"url",      required_argument, NULL,         'u'},
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
void usage2(int , const char *, ...) __attribute__((format (printf, 2, 3)));
void warnuser(const char *, ...)     __attribute__((format (printf, 1, 2)));
char readanswer(const char *answers);
#ifdef HAVE_MAGIC_H
int  file_istext(char *filename);
#endif

#if ( SUBMIT_ENABLE_CMD )
int  cmdsubmit();
#endif
#if ( SUBMIT_ENABLE_WEB )
int  websubmit();
#endif
#ifdef HAVE_CURL_CURL_H
int  getlangexts();

/* Helper function for using libcurl in websubmit() and getlangexts() */
size_t writesstream(void *ptr, size_t size, size_t nmemb, void *sptr)
{
	stringstream *s = (stringstream *) sptr;

	*s << string((char *)ptr,size*nmemb);

	return size*nmemb;
}
#endif

#if ( SUBMIT_ENABLE_CMD )
int socket_fd; /* filedescriptor of the connection to server socket */

struct addrinfo *server_ais, *server_ai; /* server adress information */
char server_addr[NI_MAXHOST];            /* server IP address string  */
#endif

int nwarnings;

/* Submission information */
string problem, language, extension, server, team, baseurl;
vector<string> filenames;
char *submitdir;

/* Language extensions */
vector<vector<string> > languages;

int main(int argc, char **argv)
{
	size_t i,j;
	int c;
	char *ptr;
	char *homedir;
	struct stat fstats;
	string filebase, fileext;
	time_t fileage;

	progname = argv[0];
	stdlog = NULL;

	if ( getenv("HOME")==NULL ) error(0,"environment variable `HOME' not set");
	homedir = getenv("HOME");

	/* Check for USERDIR and create it if nessary */
	submitdir = allocstr("%s/%s",homedir,USERDIR);
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

	/* Read defaults for server, team and baseurl from environment */
	server = string("localhost");
	baseurl = string("http://localhost/domjudge/");

	if ( getenv("SUBMITSERVER") !=NULL ) server  = string(getenv("SUBMITSERVER"));
	if ( getenv("SUBMITBASEURL")!=NULL ) baseurl = string(getenv("SUBMITBASEURL"));

	if ( getenv("USER")!=NULL ) team = string(getenv("USER"));
	if ( getenv("TEAM")!=NULL ) team = string(getenv("TEAM"));

	/* Parse command-line options */
#if ( SUBMIT_DEFAULT == 1 )
	use_websubmit = 0;
#else
	use_websubmit = 1;
#endif
	quiet =	show_help = show_version = 0;
	opterr = 0;
	while ( (c = getopt_long(argc,argv,"p:l:s:t:u:P:w::v::q",long_opts,NULL))!=-1 ) {
		switch ( c ) {
		case 0:   /* long-only option */
			break;

		case 'p': problem   = string(optarg); break;
		case 'l': extension = string(optarg); break;
		case 's': server    = string(optarg); break;
		case 't': team      = string(optarg); break;
		case 'u': baseurl   = string(optarg); break;

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

#ifdef HAVE_CURL_CURL_H
	if ( getlangexts()!=0 ) warning(0,"could not obtain language extensions");
#endif

	if ( show_help ) usage();
	if ( show_version ) version(PROGRAM,VERSION);

	if ( argc<=optind   ) usage2(0,"no file(s) specified");

	/* Process all source files */
	for(i=0; optind+(int)i<argc; i++) {
		ptr = argv[optind+i];

		/* Ignore doubly specified files */
		for(j=0; j<filenames.size(); j++) {
			if ( filenames[j]==string(ptr) ) {
				logmsg(LOG_DEBUG,"ignoring doubly specified file `%s'",ptr);
				goto nextfile;
			}
		}

		/* Stat file and do some sanity checks */
		if ( stat(ptr,&fstats)!=0 ) usage2(errno,"cannot find file `%s'",ptr);
		logmsg(LOG_DEBUG,"submission file %d: `%s'",(int)i+1,ptr);

		/* Do some sanity checks on submission file and warn user */
		nwarnings = 0;

		if ( ! (fstats.st_mode & S_IFREG) ) warnuser("`%s' is not a regular file",ptr);
		if ( ! (fstats.st_mode & S_IRUSR) ) warnuser("`%s' is not readable",ptr);
		if ( fstats.st_size==0 )            warnuser("`%s' is empty",ptr);

		if ( (fileage=(time(NULL)-fstats.st_mtime)/60)>WARN_MTIME ) {
			warnuser("`%s' has not been modified for %d minutes",ptr,(int)fileage);
		}

#ifdef HAVE_MAGIC_H
		if ( !file_istext(ptr) ) warnuser("`%s' is detected as binary/data",ptr);
#endif

		filenames.push_back(ptr);

	  nextfile:	;
	}

	/* Try to parse problem and language from first filename */
	filebase = string(gnu_basename(filenames[0].c_str()));
	if ( filebase.find('.')!=string::npos ) {
		fileext = filebase.substr(filebase.rfind('.')+1);
		filebase.erase(filebase.find('.'));

		if ( problem.empty()   ) problem   = filebase;
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
		warnuser("language `%s' not recognised",extension.c_str());
		language = extension;
	}

	if ( problem.empty()  ) usage2(0,"no problem specified");
	if ( language.empty() ) usage2(0,"no language specified");
	if ( team.empty()     ) usage2(0,"no team specified");

	if (use_websubmit) {
		if ( baseurl.empty() ) usage2(0,"no url specified");
	} else {
		if ( server.empty() ) usage2(0,"no server specified");
	}

	logmsg(LOG_DEBUG,"problem is `%s'",problem.c_str());
	logmsg(LOG_DEBUG,"language is `%s'",language.c_str());
	logmsg(LOG_DEBUG,"team is `%s'",team.c_str());
	if (use_websubmit) {
		logmsg(LOG_DEBUG,"url is `%s'",baseurl.c_str());
	} else {
		logmsg(LOG_DEBUG,"server is `%s'",server.c_str());
	}

	/* Ask user for confirmation */
	if ( ! quiet ) {
		printf("Submission information:\n");
		if ( filenames.size()==1 ) {
			printf("  filename:    %s\n",filenames[0].c_str());
		} else {
			printf("  filenames:  ");
			for(i=0; i<filenames.size(); i++) {
				printf(" %s",filenames[i].c_str());
			}
			printf("\n");
		}
		printf("  problem:     %s\n",problem.c_str());
		printf("  language:    %s\n",language.c_str());
		printf("  team:        %s\n",team.c_str());
		if (use_websubmit) {
			printf("  url:         %s\n",baseurl.c_str());
		} else {
			printf("  server/port: %s/%d\n",server.c_str(),port);
		}

		if ( nwarnings>0 ) printf("There are warnings for this submission!\a\n");
		printf("Do you want to continue? (y/n) ");
		c = readanswer("yn");
		printf("\n");
		if ( c=='n' ) error(0,"submission aborted by user");
	}

	if ( use_websubmit ) {
#if ( SUBMIT_ENABLE_WEB )
		return websubmit();
#else
		error(0,"websubmit requested, but not available");
#endif
	} else {
#if ( SUBMIT_ENABLE_CMD )
		return cmdsubmit();
#else
		error(0,"cmdsubmit requested, but not available");
#endif
	}

}

void usage()
{
	size_t i,j;

	printf("Usage: %s [OPTION]... FILENAME...\n",progname);
	printf(
"Submit a solution for a problem.\n"
"\n"
"Options (see below for more information)\n"
"  -p, --problem=PROBLEM    submit for problem PROBLEM\n"
"  -l, --language=LANGUAGE  submit in language LANGUAGE\n"
"  -v, --verbose[=LEVEL]    increase verbosity or set to LEVEL, where LEVEL\n"
"                               must be numerically specified as in 'syslog.h'\n"
"                               defaults to LOG_INFO without argument\n"
"  -q, --quiet              set verbosity to LOG_ERR and suppress user\n"
"                               input and warning/info messages\n"
"      --help               display this help and exit\n"
"      --version            output version information and exit\n"
"\n"
"The following option(s) should not be necessary for normal use\n"
#if ( SUBMIT_ENABLE_CMD )
"  -t, --team=TEAM          submit as team TEAM\n"
"  -s, --server=SERVER      submit to server SERVER\n"
"  -P, --port=PORT          connect to SERVER on tcp-port PORT\n"
#endif
#if ( SUBMIT_ENABLE_WEB )
"  -u, --url=URL            submit to webserver with base address URL\n"
#endif
#if ( SUBMIT_ENABLE_WEB && SUBMIT_ENABLE_CMD )
"  -w, --web[=0|1]          toggle or set submit to the webinterface\n"
#endif
"\n"
"Explanation of submission options:\n"
"\n"
"For PROBLEM use the ID of the problem (letter, number or short name)\n"
"in lower- or uppercase. When not specified, PROBLEM defaults to the\n"
"first FILENAME excluding the extension. For example, 'c.java' will\n"
"indicate problem 'C'.\n"
"\n");
	if ( languages.size()==0 ) {
		printf(
"For LANGUAGE use one the ID of the language (typically the main language\n"
"extension) in lower- or uppercase.\n");
	} else {
		printf(
"For LANGUAGE use one of the following extensions in lower- or uppercase:\n");
		for(i=0; i<languages.size(); i++) {
			printf("   %-15s  %s",(languages[i][0]+':').c_str(),languages[i][1].c_str());
			for(j=2; j<languages[i].size(); j++) printf(", %s",languages[i][j].c_str());
			printf("\n");
		}
	}
	printf(
"The default for LANGUAGE is the extension of FILENAME. For example,\n"
"'c.java' will indicate a Java solution.\n"
"\n"
"Examples:\n"
"\n");
	printf("Submit problem 'c' in Java:\n"
	       "    %s c.java\n\n",progname);
	printf("Submit problem 'e' in C++:\n"
	       "    %s --problem e --language=cpp ProblemE.cc\n\n",progname);
	printf("Submit problem 'hello' in C (options override the defaults from FILENAME):\n"
	       "    %s -p hello -l C HelloWorld.cpp\n\n",progname);
	printf("Submit multiple files (the problem and languare are taken from the first):\n"
	       "    %s hello.java message.java\n\n",progname);
	printf(
"The following options should not be necessary for normal use:\n"
"\n"
#if ( SUBMIT_ENABLE_CMD )
"Specify with TEAM the team ID you want to submit as. The default\n"
"value for TEAM is taken from the environment variable 'TEAM' or\n"
"your login name if 'TEAM' is not defined.\n"
"\n"
"Set SERVER to the hostname or IP-address of the submit-server.\n"
"The default value for SERVER is defined internally or otherwise\n"
"taken from the environment variable 'SUBMITSERVER', or 'localhost'\n"
"if 'SUBMITSERVER' is not defined; PORT can be used to set an alternative\n"
"TCP port to connect to.\n"
"\n"
#endif
#if ( SUBMIT_ENABLE_WEB )
"Set URL to the base address of the webinterface without the\n"
"'team/upload.php' suffix.\n"
"\n"
#endif
#if ( SUBMIT_ENABLE_WEB && SUBMIT_ENABLE_CMD )
"The TEAM/SERVER/PORT options are only used when submitting to the\n"
"commandline daemon, and URL only when using the webinterface.\n"
#endif
	);
	exit(0);
}

void usage2(int errnum, const char *mesg, ...)
{
	va_list ap;
	va_start(ap,mesg);

	vlogerror(errnum,mesg,ap);

	va_end(ap);

	printf("Type '%s --help' to get help.\n",progname);
	exit(1);
}

void warnuser(const char *warning, ...)
{
	char *str;
	va_list ap;

	va_start(ap,warning);
	str = vallocstr(warning,ap);

	nwarnings++;

	logmsg(LOG_DEBUG,"user warning #%d: %s",nwarnings,str);

	if ( ! quiet ) printf("WARNING: %s!\n",str);

	free(str);
	va_end(ap);
}

char readanswer(const char *answers)
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

#ifdef HAVE_MAGIC_H

int file_istext(char *filename)
{
	magic_t cookie;
	const char *filetype;
	int res;

	if ( (cookie = magic_open(MAGIC_MIME))==NULL ) goto magicerror;

	if ( magic_load(cookie,NULL)!=0 ) goto magicerror;

	if ( (filetype = magic_file(cookie,filename))==NULL ) goto magicerror;

	logmsg(LOG_DEBUG,"mime-type of '%s'",filetype);

	res = ( strncmp(filetype,"text/",5)==0 );

	magic_close(cookie);

	return res;

magicerror:
	warning(magic_errno(cookie),"%s",magic_error(cookie));

	return 1; // return 'text' by default on error
}

#endif /* HAVE_MAGIC_H */

#ifdef HAVE_CURL_CURL_H
int getlangexts()
{
	CURL *handle;
	CURLcode res;
	char curlerrormsg[CURL_ERROR_SIZE];
	char *url;
	stringstream curloutput;
	Json::Reader reader;
	Json::Value root, exts;

	url = strdup((baseurl+"api/languages").c_str());

	curlerrormsg[0] = 0;

	handle = curl_easy_init();
	if ( handle == NULL ) {
		warning(0,"curl_easy_init() error");
		free(url);
		return 1;
	}

/* helper macros to easily set curl options */
#define curlsetopt(opt,val) \
	if ( curl_easy_setopt(handle, CURLOPT_ ## opt, val)!=CURLE_OK ) { \
		warning(0,"setting curl option '" #opt "': %s, aborting download",curlerrormsg); \
		curl_easy_cleanup(handle); \
		free(url); \
		return 1; }

	/* Set options for post */
	curlsetopt(ERRORBUFFER,   curlerrormsg);
	curlsetopt(FAILONERROR,   1);
	curlsetopt(FOLLOWLOCATION,1);
	curlsetopt(MAXREDIRS,     10);
	curlsetopt(TIMEOUT,       timeout_secs);
	curlsetopt(URL,           url);
	curlsetopt(WRITEFUNCTION, writesstream);
	curlsetopt(WRITEDATA,     (void *)&curloutput);
	curlsetopt(USERAGENT     ,DOMJUDGE_PROGRAM " (" PROGRAM " using cURL)");

	if ( verbose >= LOG_DEBUG ) {
		curlsetopt(VERBOSE,   1);
	} else {
		curlsetopt(NOPROGRESS,1);
	}

	logmsg(LOG_INFO,"connecting to %s",url);

	if ( (res=curl_easy_perform(handle))!=CURLE_OK ) {
		warning(0,"downloading '%s': %s",url,curlerrormsg);
		curl_easy_cleanup(handle);
		free(url);
		return 1;
	}

#undef curlsetopt

	curl_easy_cleanup(handle);

	free(url);

	cout << curloutput.str() << endl;

	if ( !reader.parse(curloutput, root) ) {
		warning(0,"parsing REST API output: %s",
		        reader.getFormattedErrorMessages().c_str());
		return 1;
	}

	if ( !root.isArray() || root.size()==0 ) goto invalid_json;

	for(Json::ArrayIndex i=0; i<root.size(); i++) {
		vector<string> lang;

		lang.push_back(root[i].get("name","").asString());
		if ( lang[0]=="" ||
		     !(exts = root[i]["extensions"]) ||
		     !exts.isArray() || exts.size()==0 ) goto invalid_json;

		for(Json::ArrayIndex j=0; j<exts.size(); j++) lang.push_back(exts[j].asString());

		languages.push_back(lang);
	}

	return 0;

  invalid_json:
	warning(0,"REST API returned unexpected JSON data");
	return 1;
}

#endif /* HAVE_CURL_CURL_H */

#if ( SUBMIT_ENABLE_CMD )

int cmdsubmit()
{
	int redir_fd[3];
	int temp_fd;
	const char *args[2];
	char *template_str, *tempfile;
	vector<string> tempfiles;
	struct timeval timeout;
	struct addrinfo hints;
	char *port_str;
	int err;
	size_t i;

	/* Make tempfiles to submit */
	template_str = allocstr("%s/%s.XXXXXX.%s",submitdir,
	                        problem.c_str(),extension.c_str());
	for(i=0; i<filenames.size(); i++) {
		tempfile = strdup(template_str);
		temp_fd = mkstemps(tempfile,extension.length()+1);
		if ( temp_fd<0 || strlen(tempfile)==0 ) {
			error(errno,"mkstemps cannot create tempfile");
		}

		/* Construct copy command and execute it */
		args[0] = filenames[i].c_str();
		args[1] = tempfile;
		redir_fd[0] = redir_fd[1] = redir_fd[2] = FDREDIR_NONE;
		if ( execute(COPY_CMD,args,2,redir_fd,1)!=0 ) {
			error(0,"cannot copy `%s' to `%s'",args[0],args[1]);
		}

		if ( chmod(tempfile,USERPERMFILE)!=0 ) {
			error(errno,"setting permissions on `%s'",tempfile);
		}

		logmsg(LOG_INFO,"copied `%s' to tempfile `%s'",filenames[i].c_str(),tempfile);
		tempfiles.push_back(tempfile);
	}

	/* Connect to the submission server */
	logmsg(LOG_NOTICE,"connecting to the server (%s, %d/tcp)...",
	       server.c_str(),port);

	/* Set preferred network connection options: use both IPv4 and
 	   IPv6 by default */
	memset(&hints, 0, sizeof(hints));
	hints.ai_flags    = AI_ADDRCONFIG | AI_CANONNAME;
	hints.ai_socktype = SOCK_STREAM;

	port_str = allocstr("%d",port);
	if ( (err = getaddrinfo(server.c_str(),port_str,&hints,&server_ais)) ) {
		error(0,"getaddrinfo: %s",gai_strerror(err));
	}
	free(port_str);

	/* Try to connect to addresses for server in given order */
	socket_fd = -1;
	for(server_ai=server_ais; server_ai!=NULL; server_ai=server_ai->ai_next) {

		err = getnameinfo(server_ai->ai_addr,server_ai->ai_addrlen,server_addr,
		                  sizeof(server_addr),NULL,0,NI_NUMERICHOST);
		if ( err!=0 ) error(0,"getnameinfo: %s",gai_strerror(err));

		logmsg(LOG_DEBUG,"trying to connect to address `%s'",server_addr);

		socket_fd = socket(server_ai->ai_family,server_ai->ai_socktype,
		                   server_ai->ai_protocol);
		if ( socket_fd>=0 ) {
			if ( connect(socket_fd,server_ai->ai_addr,server_ai->ai_addrlen)==0 ) {
				break;
			} else {
				close(socket_fd);
				socket_fd = -1;
			}
		}
	}
	if ( socket_fd<0 ) error(0,"cannot connect to the server");

	/* Set socket timeout option on read/write */
	timeout.tv_sec  = timeout_secs;
	timeout.tv_usec = 0;

	if ( setsockopt(socket_fd,SOL_SOCKET,SO_SNDTIMEO,&timeout,sizeof(timeout)) < 0) {
		error(errno,"setting socket option");
	}

	if ( setsockopt(socket_fd,SOL_SOCKET,SO_RCVTIMEO,&timeout,sizeof(timeout)) < 0) {
		error(errno,"setting socket option");
	}

	logmsg(LOG_INFO,"connected, server address is `%s'",server_addr);

	receive(socket_fd);

	/* Send submission info */
	logmsg(LOG_NOTICE,"sending data...");
	sendit(socket_fd,"+team %s",team.c_str());
	receive(socket_fd);
	sendit(socket_fd,"+problem %s",problem.c_str());
	receive(socket_fd);
	sendit(socket_fd,"+language %s",extension.c_str());
	receive(socket_fd);
	for(i=0; i<filenames.size(); i++) {
		sendit(socket_fd,"+filename %s",gnu_basename(tempfiles[i].c_str()));
		receive(socket_fd);
		sendit(socket_fd,"+fileorig %s",gnu_basename(filenames[i].c_str()));
		receive(socket_fd);
	}
	sendit(socket_fd,"+done");

	/* Keep reading until end of file, then check for errors */
	while ( receive(socket_fd) );
	if ( strncasecmp(lastmesg,"done",4)!=0 ) {
		error(0,"connection closed unexpectedly");
	}

	freeaddrinfo(server_ais);

	logmsg(LOG_NOTICE,"submission successful");

    return 0;
}

#endif /* SUBMIT_ENABLE_CMD */

#if ( SUBMIT_ENABLE_WEB )

string remove_html_tags(string s)
{
	size_t p1, p2;

	while ( (p1=s.find('<',0))!=string::npos ) {
		p2 = s.find('>',p1);
		if ( p2==string::npos ) break;
		s.erase(p1,p2-p1+1);
	}

	return s;
}

int websubmit()
{
	CURL *handle;
	CURLcode res;
	char curlerrormsg[CURL_ERROR_SIZE];
	struct curl_httppost *post = NULL;
	struct curl_httppost *last = NULL;
	char *url;
	stringstream curloutput;
	string line;
	size_t pos;
	int uploadstatus_read;

	url = strdup((baseurl+"team/upload.php").c_str());

	curlerrormsg[0] = 0;

	handle = curl_easy_init();
	if ( handle == NULL ) error(0,"curl_easy_init() error");

/* helper macros to easily set curl options and fill forms */
#define curlsetopt(opt,val) \
	if ( curl_easy_setopt(handle, CURLOPT_ ## opt, val)!=CURLE_OK ) { \
		warning(0,"setting curl option '" #opt "': %s, aborting download",curlerrormsg); \
		curl_easy_cleanup(handle); \
		curl_formfree(post); \
		free(url); \
		return 1; }
#define curlformadd(nametype,namecont,valtype,valcont) \
	if ( curl_formadd(&post, &last, \
			CURLFORM_ ## nametype, namecont, \
			CURLFORM_ ## valtype, valcont, \
			CURLFORM_END) != 0 ) { \
		curl_formfree(post); \
		curl_easy_cleanup(handle); \
		free(url); \
		error(0,"libcurl could not add form field '%s'='%s'",namecont,valcont); \
	}

	/* Fill post form */

	for(size_t i=0; i<filenames.size(); i++) {
		curlformadd(COPYNAME,"code[]", FILE, filenames[i].c_str());
	}
	curlformadd(COPYNAME,"probid", COPYCONTENTS,problem.c_str());
	curlformadd(COPYNAME,"langid", COPYCONTENTS,extension.c_str());
	curlformadd(COPYNAME,"noninteractive",COPYCONTENTS,"1");
	curlformadd(COPYNAME,"submit", COPYCONTENTS,"submit");

	/* Set options for post */
	curlsetopt(ERRORBUFFER,   curlerrormsg);
	curlsetopt(FAILONERROR,   1);
	curlsetopt(FOLLOWLOCATION,1);
	curlsetopt(MAXREDIRS,     10);
	curlsetopt(TIMEOUT,       timeout_secs);
	curlsetopt(URL,           url);
	curlsetopt(HTTPPOST,      post);
	curlsetopt(HTTPGET,       0);
	curlsetopt(WRITEFUNCTION, writesstream);
	curlsetopt(WRITEDATA,     (void *)&curloutput);
	curlsetopt(USERAGENT     ,DOMJUDGE_PROGRAM " (" PROGRAM " using cURL)");

	if ( verbose >= LOG_DEBUG ) {
		curlsetopt(VERBOSE,   1);
	} else {
		curlsetopt(NOPROGRESS,1);
	}

	logmsg(LOG_NOTICE,"connecting to %s",url);

	if ( (res=curl_easy_perform(handle))!=CURLE_OK ) {
		curl_formfree(post);
		curl_easy_cleanup(handle);
		error(0,"downloading '%s': %s",url,curlerrormsg);
	}

#undef curlsetopt
#undef curlformadd

	curl_formfree(post);
	curl_easy_cleanup(handle);

	free(url);

	// Read curl output and find upload status
	uploadstatus_read = 0;
	while ( getline(curloutput,line) ) {

		// Search line for upload status or errors
 		if ( (pos=line.find(NONINTSTR,0))!=string::npos ) {
			size_t msgstart = pos+strlen(NONINTSTR);
			size_t msgend = line.find(NONINTSTR,msgstart);
			string msg = line.substr(msgstart,msgend-msgstart);
			if ( (pos=msg.find(ERRMATCH,0))!=string::npos ) {
				error(0,"webserver returned: %s",msg.erase(pos,strlen(ERRMATCH)).c_str());
			}
			if ( (pos=msg.find(WARNMATCH,0))!=string::npos ) {
				warning(0,"webserver returned: %s",msg.erase(pos,strlen(WARNMATCH)).c_str());
			}
 		}
		if ( line.find("uploadstatus",0)!=string::npos ) {
			line = remove_html_tags(line);
			if ( line.find("ERROR",0) !=string::npos ||
				 line.find("failed",0)!=string::npos ) {
				error(0,"webserver returned: %s",line.c_str());
			}
			logmsg(LOG_NOTICE,"webserver returned: %s",line.c_str());
			uploadstatus_read = 1;
		}
	}

	if ( ! uploadstatus_read ) error(0,"no upload status or error reported by webserver");

	return 0;
}
#endif /* SUBMIT_ENABLE_WEB */

//  vim:ts=4:sw=4:
