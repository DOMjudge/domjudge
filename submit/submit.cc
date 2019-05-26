/*
 * submit -- command-line submit program for solutions.
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 *
 */

#include "config.h"

#include "submit-config.h"

/* Check whether submit dependencies are available */
#if ( ! ( HAVE_CURL_CURL_H && ( HAVE_JSONCPP_JSON_JSON_H || HAVE_JSON_JSON_H ) ) )
#error "libcURL or libJSONcpp not available."
#endif

/* Standard include headers */
#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <errno.h>
#include <ctype.h>
#include <string.h>
#include <sys/stat.h>
#include <getopt.h>
#include <termios.h>
#include <curl/curl.h>
#include <curl/easy.h>
#ifdef HAVE_JSONCPP_JSON_JSON_H
#include <jsoncpp/json/json.h>
#endif
#ifdef HAVE_JSON_JSON_H
#include <json/json.h>
#endif
#ifdef HAVE_MAGIC_H
#include <magic.h>
#endif


/* C++ includes for easy string handling */
#include <iostream>
#include <sstream>
#include <string>
#include <vector>
#include <algorithm>
using namespace std;

/* These defines are needed in 'version' and 'logmsg' */
#define DOMJUDGE_PROGRAM "DOMjudge/" DOMJUDGE_VERSION
#define PROGRAM "submit"
#define VERSION DOMJUDGE_VERSION "/" REVISION

/* Use a specific API version, set to empty string for default */
#define API_VERSION "v4/"

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
int assume_yes;
int show_help;
int show_version;

struct option const long_opts[] = {
	{"problem",     required_argument, NULL,         'p'},
	{"language",    required_argument, NULL,         'l'},
	{"url",         required_argument, NULL,         'u'},
	{"verbose",     optional_argument, NULL,         'v'},
	{"contest",     required_argument, NULL,         'c'},
	{"entry_point", required_argument, NULL,         'e'},
	{"quiet",       no_argument,       NULL,         'q'},
	{"assume_yes",  no_argument,       NULL,         'y'},
	{"help",        no_argument,       &show_help,    1 },
	{"version",     no_argument,       &show_version, 1 },
	{ NULL,         0,                 NULL,          0 }
};

void version();
void usage();
void curl_setup();
void curl_cleanup();
void usage2(int , const char *, ...) __attribute__((format (printf, 2, 3)));
void warnuser(const char *, ...)     __attribute__((format (printf, 1, 2)));
char readanswer(const char *answers);
#ifdef HAVE_MAGIC_H
bool file_istext(char *filename);
#endif

bool doAPIsubmit();

Json::Value doAPIrequest(const char *);
bool readlanguages();
bool readproblems();
bool readcontests();

/* Helper function for using libcurl in doAPIsubmit() and doAPIrequest() */
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

const int nHTML_entities = 5;
const char HTML_entities[nHTML_entities][2][8] = {
	{"&amp;", "&"},
	{"&quot;", "\""},
	{"&apos;", "'"},
	{"&lt;", "<"},
	{"&gt;", ">"}};

std::string decode_HTML_entities(std::string str)
{
	string res;
	unsigned int i, j;

	for(i=0; i<str.length(); i++) {
		for(j=0; j<nHTML_entities; j++) {
			if ( str.substr(i,strlen(HTML_entities[j][0]))==HTML_entities[j][0] ) {
				res += HTML_entities[j][1];
				i += strlen(HTML_entities[j][0]) - 1;
				break;
			}
		}
		if ( j>=nHTML_entities ) res += str[i];
	}

	return res;
}

int nwarnings;

/* Submission information */
string contestid, langid, probid, baseurl;
vector<string> filenames;
char *entry_point;
char *submitdir;

/* Active contests */
struct contest {
	string id, name, shortname;
};
vector<contest> contests;
contest mycontest;

/* Languages */
struct language {
	string id, name;
	bool require_entry_point;
	vector<string> extensions;
};
vector<language> languages;
language mylanguage;

/* Problems */
struct problem {
	string id, label, name;
};
vector<problem> problems;
problem myproblem;

CURL *handle;
char curlerrormsg[CURL_ERROR_SIZE];

string kotlin_base_entry_point(string filebase)
{
	if ( filebase.empty() ) return "_";
	for(size_t i=0; i<filebase.length(); i++) {
		if ( !isalnum(filebase[i]) ) filebase[i] = '_';
	}
	if ( isalpha(filebase[0]) ) {
		filebase[0] = toupper(filebase[0]);
	} else {
		filebase = '_' + filebase;
	}
	return filebase;
}

int main(int argc, char **argv)
{
	size_t i,j;
	int c;
	char *ptr;
	char *homedir;
	struct stat fstats;
	string filebase, fileext;
	time_t fileage;
	string require_entry_point;

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

	curl_setup();

	logmsg(LOG_INFO,"started");

	/* Read default for baseurl and contest from environment */
	baseurl = string(BASEURL);
	contestid = "";
	if ( getenv("SUBMITBASEURL")!=NULL ) baseurl = string(getenv("SUBMITBASEURL"));
	if ( getenv("SUBMITCONTEST")!=NULL ) contestid = string(getenv("SUBMITCONTEST"));

	quiet = show_help = show_version = 0;
	opterr = 0;
	while ( (c = getopt_long(argc,argv,"p:l:u:c:e:v::qy",long_opts,NULL))!=-1 ) {
		logmsg(LOG_DEBUG, "read option `%c' with argument `%s'",
		       c, ( optarg==NULL ? "NULL" : optarg ));
		switch ( c ) {
		case 0:   /* long-only option */
			break;

		case 'p': probid      = string(optarg); break;
		case 'l': langid      = string(optarg); break;
		case 'u': baseurl     = string(optarg); break;
		case 'c': contestid   = string(optarg); break;
		case 'e': entry_point = strdup(optarg); break;

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
		case 'y': /* assume_yes option */
			assume_yes = 1;
			break;
		case ':': /* getopt error */
		case '?':
			usage2(0,"unknown option or missing argument `%c'",optopt);
			break;
		default:
			error(0,"getopt returned character code `%c' ??",c);
		}
	}

	logmsg(LOG_INFO,"set verbosity to %d", verbose);

	/* Make sure that baseurl terminates with a '/' for later concatenation. */
	if ( !baseurl.empty() && baseurl[baseurl.length()-1]!='/' ) baseurl += '/';

	if ( !readcontests() ) warning(0,"could not obtain active contests");

	if ( contestid.empty() ) {
		if ( contests.size()==0 ) {
			warnuser("no active contests found (and no contest specified)");
		}
		if ( contests.size()==1 ) {
			mycontest = contests[0];
		}
		if ( contests.size()>1 ) {
			warnuser("multiple active contests found, please specify one");
		}
	} else {
		contestid = stringtolower(contestid);
		for(i=0; i<contests.size(); i++) {
			if ( stringtolower(contests[i].id) == contestid || stringtolower(contests[i].shortname) == contestid ) {
				mycontest = contests[i];
				break;
			}
		}
	}

	bool languagesRead = false;
	bool problemsRead  = false;
	if ( !mycontest.id.empty() ) {
		languagesRead = readlanguages();
		problemsRead = readproblems();
	}

	if ( show_help ) usage();
	if ( show_version ) version(PROGRAM,VERSION);

	if ( mycontest.id.empty() ) usage2(0,"no (valid) contest specified");

	if ( !languagesRead ) warning(0,"could not obtain language data");

	if ( !problemsRead ) warning(0,"could not obtain problem data");

	if ( argc<=optind ) usage2(0,"no file(s) specified");

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

		if ( probid.empty() ) probid = filebase;
		if ( langid.empty() ) langid = fileext;
	}

	/* Check for languages matching file extension */
	langid = stringtolower(langid);
	for(i=0; i<languages.size(); i++) {
		for(j=0; j<languages[i].extensions.size(); j++) {
			if ( stringtolower(languages[i].extensions[j]) == langid ) {
				mylanguage = languages[i];
				goto lang_found;
			}
		}
	}
lang_found:

	probid = stringtolower(probid);
	for(i=0; i<problems.size(); i++) {
		if ( stringtolower(problems[i].id) == probid || stringtolower(problems[i].label) == probid ) {
			myproblem = problems[i];
			break;
		}
	}

	if ( myproblem.id.empty()  ) usage2(0,"no problem specified or detected");
	if ( mylanguage.id.empty() ) usage2(0,"no language specified or detected");
	if ( baseurl.empty()       ) usage2(0,"no url specified");

	/* Guess entry point if not already specified. */
	if ( entry_point==NULL && mylanguage.require_entry_point ) {
		if ( mylanguage.name == "Java" ) {
			entry_point = strdup(filebase.c_str());
		} else if ( mylanguage.name == "Kotlin" ) {
			entry_point = strdup(string(kotlin_base_entry_point(filebase) + "Kt").c_str());
		} else if ( mylanguage.name == "Python 2" ||
		            mylanguage.name == "Python 2 (pypy)" ||
		            mylanguage.name == "Python 3" ) {
			entry_point = strdup(string(filebase + "." + fileext).c_str());
		}
	}

	if ( entry_point==NULL && mylanguage.require_entry_point ) {
		error(0, "Entry point required but not specified nor detected.");
	}

	logmsg(LOG_DEBUG,"contest is `%s'",mycontest.shortname.c_str());
	logmsg(LOG_DEBUG,"problem is `%s'",myproblem.label.c_str());
	logmsg(LOG_DEBUG,"language is `%s'",mylanguage.name.c_str());
	logmsg(LOG_DEBUG,"entry_point is `%s'",entry_point);
	logmsg(LOG_DEBUG,"url is `%s'",baseurl.c_str());

	/* Ask user for confirmation */
	if ( ! assume_yes ) {
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
		printf("  contest:     %s\n",mycontest.shortname.c_str());
		printf("  problem:     %s\n",myproblem.label.c_str());
		printf("  language:    %s\n",mylanguage.name.c_str());
		if ( entry_point!=NULL ) {
			printf("  entry_point: %s\n",entry_point);
		}
		printf("  url:         %s\n",baseurl.c_str());

		if ( nwarnings>0 ) printf("There are warnings for this submission!\a\n");
		printf("Do you want to continue? (y/n) ");
		c = readanswer("yn");
		printf("\n");
		if ( c=='n' ) error(0,"submission aborted by user");
	}

	doAPIsubmit();
	return 0;
}

void curl_setup()
{
	handle = curl_easy_init();
	if ( handle == NULL ) {
		error(0,"curl_easy_init() error");
	}

	curl_easy_setopt(handle, CURLOPT_WRITEFUNCTION, writesstream);
	curl_easy_setopt(handle, CURLOPT_ERRORBUFFER,   curlerrormsg);
	curl_easy_setopt(handle, CURLOPT_FAILONERROR,   0);
	curl_easy_setopt(handle, CURLOPT_FOLLOWLOCATION,1);
	curl_easy_setopt(handle, CURLOPT_MAXREDIRS,     10);
	curl_easy_setopt(handle, CURLOPT_NETRC,         CURL_NETRC_OPTIONAL);
	curl_easy_setopt(handle, CURLOPT_TIMEOUT,       timeout_secs);
	curl_easy_setopt(handle, CURLOPT_USERAGENT,     DOMJUDGE_PROGRAM " (" PROGRAM " using cURL)");

	if ( verbose >= LOG_DEBUG ) {
		curl_easy_setopt(handle, CURLOPT_VERBOSE,   1);
	} else {
		curl_easy_setopt(handle, CURLOPT_NOPROGRESS,1);
	}
}

void curl_cleanup()
{
	// Passing a null handle is a no-op, so this is safe.
	curl_easy_cleanup(handle);
}


void usage()
{
	size_t i,j;

	printf("Usage: %s [OPTION]... FILENAME...\n",progname);
	printf(
"Submit a solution for a problem.\n"
"\n"
"Options (see below for more information):\n"
"  -c  --contest=CONTEST          submit for contest with ID or short name CONTEST.\n"
"                                     Defaults to the value of the\n"
"                                     environment variable 'SUBMITCONTEST'.\n"
"                                     Mandatory when more than one contest is active.\n"
"  -p, --problem=PROBLEM          submit for problem with ID or label PROBLEM\n"
"  -l, --language=LANGUAGE        submit in language with ID LANGUAGE\n"
"  -e, --entry_point=ENTRY_POINT  set an explicit entry_point, e.g. the java main class\n"
"  -v, --verbose[=LEVEL]          increase verbosity or set to LEVEL, where LEVEL\n"
"                                     must be numerically specified as in 'syslog.h'\n"
"                                     defaults to LOG_INFO without argument\n"
"  -q, --quiet                    suppress warning/info messages, set verbosity=LOG_ERR\n"
"  -y, --assume-yes               suppress user input and assume yes\n"
"      --help                     display this help and exit\n"
"      --version                  output version information and exit\n"
"\n"
"The following option(s) should not be necessary for normal use:\n"
"  -u, --url=URL                  submit to server with base address URL\n"
"\n"
"Explanation of submission options:\n"
"\n");
	if ( contests.size()<=1 ) {
		printf(
"For CONTEST use the ID or short name as shown in the top-right contest\n"
"selection box in the webinterface.\n");
		if ( contests.size()==1 ) {
			printf("Currently this defaults to the only active contest '%s'.\n",
			       contests[0].shortname.c_str());
		}
		printf("\n");
	}
	if ( contests.size()>=2 ) {
		printf(
"For CONTEST use one of the following:\n");
		for(i=0; i<contests.size(); i++) {
			printf("   %-10s - %s\n",contests[i].shortname.c_str(),contests[i].name.c_str());
		}
		printf("\n");
	}
	if ( problems.size()==0 ) {
		printf(
"For PROBLEM use the label as on the scoreboard.\n");
	} else {
		printf(
"For PROBLEM use one of the following:\n");
		for(i=0; i<problems.size(); i++) {
			printf("   %-10s - %s\n",problems[i].label.c_str(),problems[i].name.c_str());
		}
	}
	printf("\n");
	printf(
"When not specified, PROBLEM defaults to the first FILENAME excluding the\n"
"extension. For example, 'B.java' will indicate problem 'B'.\n"
"\n");
	if ( languages.size()==0 ) {
		printf(
"For LANGUAGE use the ID or a common extension in lower- or uppercase.\n");
	} else {
		printf(
"For LANGUAGE use one of the following IDs/extensions in lower- or uppercase:\n");
		for(i=0; i<languages.size(); i++) {
			printf("   %-20s  %s",(languages[i].name+':').c_str(),languages[i].extensions[0].c_str());
			for(j=1; j<languages[i].extensions.size(); j++) {
				printf(", %s",languages[i].extensions[j].c_str());
			}
			printf("\n");
		}
	}
	printf("\n");
	printf(
"The default for LANGUAGE is the extension of FILENAME. For example,\n"
"'B.java' will indicate a Java solution.\n"
"\n"
"Set URL to the base address of the webinterface without the 'api/' suffix.\n");
	if ( !baseurl.empty() ) {
		printf("The (pre)configured URL is '%s'.\n",baseurl.c_str());
	}
	printf(
"\n"
"Examples:\n"
"\n");
	printf("Submit problem 'b' in Java:\n"
	       "    %s b.java\n\n",progname);
	printf("Submit problem 'z' in C# for contest 'demo':\n"
	       "    %s --contest=demo z.cs\n\n",progname);
	printf("Submit problem 'e' in C++:\n"
	       "    %s --problem e --language=cpp ProblemE.cc\n\n",progname);
	printf("Submit problem 'hello' in C (options override the defaults from FILENAME):\n"
	       "    %s -p hello -l C HelloWorld.cpp\n\n",progname);
	printf("Submit multiple files (the problem and language are taken from the first):\n"
	       "    %s hello.java message.java\n\n",progname);
	curl_cleanup();
	exit(0);
}

void usage2(int errnum, const char *mesg, ...)
{
	va_list ap;
	va_start(ap,mesg);

	vlogerror(errnum,mesg,ap);

	va_end(ap);

	printf("Type '%s --help' to get help.\n",progname);
	curl_cleanup();
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

bool file_istext(char *filename)
{
	magic_t cookie;
	const char *filetype;
	bool res;

	if ( (cookie = magic_open(MAGIC_MIME|MAGIC_SYMLINK))==NULL ) goto magicerror;

	if ( magic_load(cookie,NULL)!=0 ) goto magicerror;

	if ( (filetype = magic_file(cookie,filename))==NULL ) goto magicerror;

	logmsg(LOG_DEBUG,"mime-type of '%s'",filetype);

	res = ( strncmp(filetype,"text/",5)==0 );

	magic_close(cookie);

	return res;

magicerror:
	warning(magic_errno(cookie),"%s",magic_error(cookie));

	return true; // return 'text' by default on error
}

#endif /* HAVE_MAGIC_H */

/*
 * Make an API call 'funcname'. An error is thrown when the call fails.
 */
Json::Value doAPIrequest(const char *funcname)
{
	CURLcode res;
	char *url;
	stringstream curloutput;
	Json::Reader reader;
	Json::Value result;
	long http_code;
	string line;

	url = strdup((baseurl+"api/"+API_VERSION+string(funcname)).c_str());

	curlerrormsg[0] = 0;

	curl_easy_setopt(handle, CURLOPT_URL,           url);
	curl_easy_setopt(handle, CURLOPT_WRITEDATA,     (void *)&curloutput);

	logmsg(LOG_INFO,"connecting to %s",url);

	if ( (res=curl_easy_perform(handle))!=CURLE_OK ) {
		error(0,"'%s': %s",url,curlerrormsg);
	}

	free(url);

	// The connection worked, but we may have received an HTTP error
	curl_easy_getinfo(handle, CURLINFO_RESPONSE_CODE, &http_code);
	if ( http_code >= 300 ) {
		while ( getline(curloutput,line) ) {
			printf("%s\n", decode_HTML_entities(line).c_str());
		}
		if ( http_code == 401 ) {
			error(0, "Authentication failed. Please check your DOMjudge credentials.");
		} else {
			error(0, "API request %s failed (code %li)", funcname, http_code);
		}
	}

	logmsg(LOG_DEBUG,"API call '%s' returned:\n%s\n",funcname,curloutput.str().c_str());

	if ( !reader.parse(curloutput, result) ) {
		error(0,"parsing REST API output: %s",
		        reader.getFormattedErrorMessages().c_str());
	}

	return result;
}

bool readlanguages()
{
	Json::Value res, exts;

	string endpoint = "contests/" + mycontest.id + "/languages";
	res = doAPIrequest(endpoint.c_str());

	if (!res.isArray()) return false;

	for(Json::ArrayIndex i=0; i<res.size(); i++) {
		language lang;

		lang.id   = res[i]["id"].asString();
		lang.name = res[i]["name"].asString();
		lang.require_entry_point = res[i]["require_entry_point"].asBool();
		if ( lang.id=="" ||
		     !(exts = res[i]["extensions"]) ||
		     !exts.isArray() || exts.size()==0 ) {
			warning(0,"REST API returned unexpected JSON data for language %d",(int)i);
			return false;
		}

		lang.extensions.push_back(lang.id);
		for(Json::ArrayIndex j=0; j<exts.size(); j++) {
			lang.extensions.push_back(exts[j].asString());
		}
		vector<string>::iterator last = unique(lang.extensions.begin(),lang.extensions.end());
		lang.extensions.erase(last, lang.extensions.end());

		languages.push_back(lang);
	}

	logmsg(LOG_INFO,"read %d languages from the API",(int)languages.size());

	return true;
}

bool readproblems()
{
	Json::Value res;

	string endpoint = "contests/" + mycontest.id + "/problems";
	res = doAPIrequest(endpoint.c_str());

	if(!res.isArray()) return false;

	for(Json::ArrayIndex i=0; i<res.size(); i++) {
		problem prob;

		prob.id    = res[i]["id"].asString();
		prob.label = res[i]["label"].asString();
		prob.name  = res[i]["name"].asString();
		if ( prob.id=="" || prob.label=="" ) {
			warning(0,"REST API returned unexpected JSON data for problem %d",(int)i);
			return false;
		}

		problems.push_back(prob);
	}

	logmsg(LOG_INFO,"read %d problems from the API",(int)problems.size());

	return true;
}

bool readcontests()
{
	Json::Value res;

	res = doAPIrequest("contests");

	if(!res.isArray()) return false;

	for(Json::ArrayIndex i=0; i<res.size(); i++) {
		contest cont;

		cont.id        = res[i]["id"].asString();
		cont.shortname = res[i]["shortname"].asString();
		cont.name      = res[i]["name"].asString();
		if ( cont.id=="" || cont.shortname=="" ) {
			warning(0,"REST API returned unexpected JSON data for contests");
			return false;
		}

		contests.push_back(cont);
	}

	logmsg(LOG_INFO,"read %d contests from the API",(int)contests.size());

	return true;
}

bool doAPIsubmit()
{
	CURLcode res;
	struct curl_httppost *post = NULL;
	struct curl_httppost *last = NULL;
	long http_code;
	char *url;
	stringstream curloutput;
	string line;
	Json::Reader reader;
	Json::Value root;

	url = strdup((baseurl + "api/" + API_VERSION + "contests/" + mycontest.id + "/submissions").c_str());

	curlerrormsg[0] = 0;

	/* Fill post form */
	for(size_t i=0; i<filenames.size(); i++) {
		curl_formadd(&post, &last, CURLFORM_COPYNAME, "code[]",
			CURLFORM_FILE, filenames[i].c_str(), CURLFORM_END);
	}
	curl_formadd(&post, &last, CURLFORM_COPYNAME, "problem",
		CURLFORM_COPYCONTENTS, myproblem.id.c_str(), CURLFORM_END);
	curl_formadd(&post, &last, CURLFORM_COPYNAME, "language",
		CURLFORM_COPYCONTENTS, mylanguage.id.c_str(), CURLFORM_END);
	if ( entry_point!=NULL ) {
		curl_formadd(&post, &last, CURLFORM_COPYNAME, "entry_point",
			CURLFORM_COPYCONTENTS, entry_point, CURLFORM_END);
	}

	/* Set options for post */
	curl_easy_setopt(handle, CURLOPT_HTTPPOST,      post);
	curl_easy_setopt(handle, CURLOPT_URL,           url);
	curl_easy_setopt(handle, CURLOPT_WRITEDATA,     (void *)&curloutput);

	logmsg(LOG_INFO,"connecting to %s",url);

	// Something went wrong when connecting to the API
	if ( (res=curl_easy_perform(handle))!=CURLE_OK ) {
		curl_formfree(post);
		curl_cleanup();
		free(url);
		error(0,"'%s': %s",url,curlerrormsg);
	}

	curl_formfree(post);
	free(url);

	logmsg(LOG_DEBUG,"API call 'submissions' returned:\n%s\n",curloutput.str().c_str());

	// The connection worked, but we may have received an HTTP error
	curl_easy_getinfo(handle, CURLINFO_RESPONSE_CODE, &http_code);
	curl_cleanup();
	if ( http_code >= 300 ) {
		while ( getline(curloutput,line) ) {
			printf("%s\n", decode_HTML_entities(line).c_str());
		}
		if ( http_code == 401 ) {
			error(0, "Authentication failed. Please check your DOMjudge credentials.");
		} else {
			error(0, "Submission failed (code %li)", http_code);
		}
	}

	// We got a successful HTTP response. It worked.
	// But check that we indeed received a submission ID.
	if ( !reader.parse(curloutput, root) ) {
		error(0,"parsing REST API output: %s",
		        reader.getFormattedErrorMessages().c_str());
	}

	if ( !root.isInt() ) {
		error(0,"REST API returned unexpected JSON data");
	}

	logmsg(LOG_NOTICE,"Submission received, id = s%i", root.asInt());

	return true;
}

//  vim:ts=4:sw=4:
