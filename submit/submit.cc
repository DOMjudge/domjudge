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

/* Check whether submit dependencies are available */
#if ( ! ( HAVE_CURL_CURL_H && HAVE_JSONCPP_JSON_JSON_H ) )
#error "libcURL or libJSONcpp not available."
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
#include <curl/curl.h>
#include <curl/easy.h>
#include <jsoncpp/json/json.h>
#ifdef HAVE_MAGIC_H
#include <magic.h>
#endif


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

/* Include GNU version of basename() */
#include "basename.h"

const int timeout_secs = 60; /* seconds before send/receive timeouts with an error */

/* Variables defining logmessages verbosity to stderr/logfile */
extern int verbose;
extern int loglevel;

char *logfile;

const char *progname;

int quiet;
int show_help;
int show_version;

struct option const long_opts[] = {
	{"problem",  required_argument, NULL,         'p'},
	{"language", required_argument, NULL,         'l'},
	{"team",     required_argument, NULL,         't'},
	{"url",      required_argument, NULL,         'u'},
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

int  websubmit();
int  getlangexts();

/* Helper function for using libcurl in websubmit() and getlangexts() */
size_t writesstream(void *ptr, size_t size, size_t nmemb, void *sptr)
{
	stringstream *s = (stringstream *) sptr;

	*s << string((char *)ptr,size*nmemb);

	return size*nmemb;
}

std::string stringtolower(std::string str)
{
	unsigned int i;

	for(i=0; i<str.length(); i++) str[i] = tolower(str[i]);

	return str;
}

int nwarnings;

/* Submission information */
string problem, language, extension, team, baseurl;
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

	/* Read defaults for user, team and baseurl from environment */
	baseurl = string("http://localhost/domjudge/");

	if ( getenv("SUBMITBASEURL")!=NULL ) baseurl = string(getenv("SUBMITBASEURL"));

	if ( getenv("USER")!=NULL ) team = string(getenv("USER"));
	if ( getenv("TEAM")!=NULL ) team = string(getenv("TEAM"));

	quiet =	show_help = show_version = 0;
	opterr = 0;
	while ( (c = getopt_long(argc,argv,"p:l:t:u:v::q",long_opts,NULL))!=-1 ) {
		switch ( c ) {
		case 0:   /* long-only option */
			break;

		case 'p': problem   = string(optarg); break;
		case 'l': extension = string(optarg); break;
		case 't': team      = string(optarg); break;
		case 'u': baseurl   = string(optarg); break;

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

#if HAVE_CURL_CURL_H && HAVE_JSONCPP_JSON_JSON_H
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
	if ( baseurl.empty()  ) usage2(0,"no url specified");

	logmsg(LOG_DEBUG,"problem is `%s'",problem.c_str());
	logmsg(LOG_DEBUG,"language is `%s'",language.c_str());
	logmsg(LOG_DEBUG,"team is `%s'",team.c_str());
	logmsg(LOG_DEBUG,"url is `%s'",baseurl.c_str());

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
		printf("  url:         %s\n",baseurl.c_str());

		if ( nwarnings>0 ) printf("There are warnings for this submission!\a\n");
		printf("Do you want to continue? (y/n) ");
		c = readanswer("yn");
		printf("\n");
		if ( c=='n' ) error(0,"submission aborted by user");
	}

	return websubmit();
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
"first FILENAME excluding the extension. For example, 'B.java' will\n"
"indicate problem 'B'.\n"
"\n");
	if ( languages.size()==0 ) {
		printf(
"For LANGUAGE use the ID of the language (typically the main language\n"
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
"'B.java' will indicate a Java solution.\n"
"\n"
"Examples:\n"
"\n");
	printf("Submit problem 'b' in Java:\n"
	       "    %s b.java\n\n",progname);
	printf("Submit problem 'e' in C++:\n"
	       "    %s --problem e --language=cpp ProblemE.cc\n\n",progname);
	printf("Submit problem 'hello' in C (options override the defaults from FILENAME):\n"
	       "    %s -p hello -l C HelloWorld.cpp\n\n",progname);
	printf("Submit multiple files (the problem and language are taken from the first):\n"
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
"'api/' suffix.\n"
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
	int c;

	/* save the terminal settings for stdin */
	tcgetattr(STDIN_FILENO,&old_termio);
	new_termio = old_termio;

	/* disable canonical mode (buffered i/o) and local echo */
	new_termio.c_lflag &= (~ICANON & ~ECHO);
	tcsetattr(STDIN_FILENO,TCSANOW,&new_termio);

	while ( true ) {
		c = getchar();
		if ( c==EOF ) error(0,"in readanswer: error or EOF");
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

	return (char) c;
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

#if HAVE_CURL_CURL_H && HAVE_JSONCPP_JSON_JSON_H
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
		warning(0,"setting curl option '" #opt "': %s, aborting",curlerrormsg); \
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

#endif /* HAVE_CURL_CURL_H && HAVE_JSONCPP_JSON_JSON_H */

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
	long http_code;
	char curlerrormsg[CURL_ERROR_SIZE];
	struct curl_httppost *post = NULL;
	struct curl_httppost *last = NULL;
	char *url;
	stringstream curloutput;
	string line;
	Json::Reader reader;
	Json::Value root;

	url = strdup((baseurl+"api/submissions").c_str());

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
	curlformadd(COPYNAME,"shortname", COPYCONTENTS,problem.c_str());
	curlformadd(COPYNAME,"langid", COPYCONTENTS,extension.c_str());

	/* Set options for post */
	curlsetopt(ERRORBUFFER,   curlerrormsg);
	curlsetopt(FOLLOWLOCATION,1);
	curlsetopt(MAXREDIRS,     10);
	curlsetopt(TIMEOUT,       timeout_secs);
	curlsetopt(URL,           url);
	curlsetopt(NETRC,         CURL_NETRC_OPTIONAL);
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

	// Something went wrong when connecting to the API
	if ( (res=curl_easy_perform(handle))!=CURLE_OK ) {
		curl_formfree(post);
		curl_easy_cleanup(handle);
		error(0,"'%s': %s",url,curlerrormsg);
	}

	free(url);

	// The connection worked, but we may have received an HTTP error
	curl_easy_getinfo(handle, CURLINFO_RESPONSE_CODE, &http_code);
	if ( http_code >= 300 ) { 
		while ( getline(curloutput,line) ) {
			printf("%s\n", line.c_str());
		}
		curl_formfree(post);
		curl_easy_cleanup(handle);
		error(0, "Submission failed.");
	}

#undef curlsetopt
#undef curlformadd
	// We got a successful HTTP response. It worked.
	// But check that we indeed received a submission ID.
	if ( !reader.parse(curloutput, root) ) {
		curl_formfree(post);
		curl_easy_cleanup(handle);
		error(0,"parsing REST API output: %s",
		        reader.getFormattedErrorMessages().c_str());
	}

	if ( !root.isInt() ) {
		curl_formfree(post);
		curl_easy_cleanup(handle);
		error(0,"REST API returned unexpected JSON data");
	}

	logmsg(LOG_NOTICE,"Submission received, id = s%i", root.asInt());

	return 0;
}

//  vim:ts=4:sw=4:
