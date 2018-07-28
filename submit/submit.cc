/*
 * submit -- command-line submit program for solutions.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
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
int show_help;
int show_version;

struct option const long_opts[] = {
	{"problem",     required_argument, NULL,         'p'},
	{"language",    required_argument, NULL,         'l'},
	{"url",         required_argument, NULL,         'u'},
	{"verbose",     optional_argument, NULL,         'v'},
	{"contest",     required_argument, NULL,         'c'},
	{"entry_point", optional_argument, NULL,         'e'},
	{"quiet",       no_argument,       NULL,         'q'},
	{"help",        no_argument,       &show_help,    1 },
	{"version",     no_argument,       &show_version, 1 },
	{ NULL,         0,                 NULL,          0 }
};

void version();
void usage();
void usage2(int , const char *, ...) __attribute__((format (printf, 2, 3)));
void warnuser(const char *, ...)     __attribute__((format (printf, 1, 2)));
char readanswer(const char *answers);
#ifdef HAVE_MAGIC_H
bool file_istext(char *filename);
#endif

bool websubmit();

Json::Value doAPIrequest(const char *, int);
bool readlanguages();
bool readcontests();

/* Helper function for using libcurl in websubmit() and doAPIrequest() */
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
string problem, language, extension, baseurl, contest, entry_point;
vector<string> filenames;
char *submitdir;

/* Language extensions */
vector<vector<string> > languages;

/* Active contests: shortname,name */
vector<vector<string> > contests;

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

	logmsg(LOG_INFO,"started");

	/* Read default for baseurl and contest from environment */
	baseurl = string(BASEURL);
	contest = "";
	if ( getenv("SUBMITBASEURL")!=NULL ) baseurl = string(getenv("SUBMITBASEURL"));
	if ( getenv("SUBMITCONTEST")!=NULL ) contest = string(getenv("SUBMITCONTEST"));

	quiet =	show_help = show_version = 0;
	opterr = 0;
	while ( (c = getopt_long(argc,argv,"p:l:u:c:e:v::q",long_opts,NULL))!=-1 ) {
		switch ( c ) {
		case 0:   /* long-only option */
			break;

		case 'p': problem     = string(optarg); break;
		case 'l': extension   = string(optarg); break;
		case 'u': baseurl     = string(optarg); break;
		case 'c': contest     = string(optarg); break;
		case 'e': entry_point = string(optarg); break;

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

	if ( !readlanguages() ) warning(0,"could not obtain language data");
	if ( !readcontests() ) warning(0,"could not obtain active contests");

	if ( show_help ) usage();
	if ( show_version ) version(PROGRAM,VERSION);

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

		if ( problem.empty()   ) problem   = filebase;
		if ( extension.empty() ) extension = fileext;
	}

	/* Check for languages matching file extension */
	extension = stringtolower(extension);
	for(i=0; i<languages.size(); i++) {
		for(j=2; j<languages[i].size(); j++) {
			if ( languages[i][j]==extension ) {
				language  = languages[i][0];
				require_entry_point = languages[i][1];
				extension = languages[i][2];
			}
		}
	}

	if ( language.empty() ) {
		warnuser("language `%s' not recognised",extension.c_str());
		language = extension;
	}

	if ( contest.empty() ) {
		if ( contests.size()==0 ) {
			warnuser("no active contests found (and no contest specified)");
		}
		if ( contests.size()==1 ) {
			contest = contests[0][0];
		}
		if ( contests.size()>1 ) {
			warnuser("multiple active contests found, please specify one");
		}
	}

	if ( contest.empty()  ) usage2(0,"no contest specified");
	if ( problem.empty()  ) usage2(0,"no problem specified");
	if ( language.empty() ) usage2(0,"no language specified");
	if ( baseurl.empty()  ) usage2(0,"no url specified");

	/* Make sure that baseurl terminates with a '/' for later concatenation. */
	if ( baseurl[baseurl.length()-1]!='/' ) baseurl += '/';

	/* Guess entry point if not already specified. */
	if ( entry_point.empty() && require_entry_point == "true" ) {
		if ( language == "Java" ) {
			entry_point = filebase;
		} else if ( language == "Kotlin" ) {
			entry_point = kotlin_base_entry_point(filebase) + "Kt";
		} else if ( language == "Python 2" || language == "Python 2 (pypy)" || language == "Python 3" ) {
			entry_point = filebase + "." + fileext;
		}
	}

	if ( entry_point.empty() && require_entry_point == "true" ) {
		error(0, "Entry point required but not specified nor detected.");
	}

	logmsg(LOG_DEBUG,"contest is `%s'",contest.c_str());
	logmsg(LOG_DEBUG,"problem is `%s'",problem.c_str());
	logmsg(LOG_DEBUG,"language is `%s'",language.c_str());
	logmsg(LOG_DEBUG,"entry_point is `%s'",entry_point.c_str());
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
		printf("  contest:     %s\n",contest.c_str());
		printf("  problem:     %s\n",problem.c_str());
		printf("  language:    %s\n",language.c_str());
		if ( !entry_point.empty() ) {
			printf("  entry_point: %s\n",entry_point.c_str());
		}
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
"  -c  --contest=CONTEST          submit for contest with identifier name CONTEST.\n"
"                                     Defaults to the value of the\n"
"                                     environment variable 'SUBMITCONTEST'.\n"
"                                     Mandatory when more than one contest is active.\n"
"  -p, --problem=PROBLEM          submit for problem PROBLEM\n"
"  -l, --language=LANGUAGE        submit in language LANGUAGE\n"
"  -e, --entry_point=ENTRY_POINT  set an explicit entry_point, e.g. the java main class\n"
"  -v, --verbose[=LEVEL]          increase verbosity or set to LEVEL, where LEVEL\n"
"                                     must be numerically specified as in 'syslog.h'\n"
"                                     defaults to LOG_INFO without argument\n"
"  -q, --quiet                    set verbosity to LOG_ERR and suppress user\n"
"                                     input and warning/info messages\n"
"      --help                     display this help and exit\n"
"      --version                  output version information and exit\n"
"\n"
"The following option(s) should not be necessary for normal use\n"
"  -u, --url=URL            submit to webserver with base address URL\n"
"\n"
"Explanation of submission options:\n"
"\n");
	if ( contests.size()<=1 ) {
		printf(
"For CONTEST use the short name as shown in the top-right contest selection box\n"
"in the webinterface.");
		if ( contests.size()==1 ) {
			printf(" Currently this defaults to the only active contest '%s'.",
			       contests[0][0].c_str());
		}
		printf("\n\n");
	}
	if ( contests.size()>=2 ) {
		printf(
"For CONTEST use one of the following:\n");
		for(i=0; i<contests.size(); i++) {
			printf("   %-15s  %s\n",contests[i][0].c_str(),contests[i][1].c_str());
		}
		printf("\n");
	}
	printf(
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
			printf("   %-15s  %s",(languages[i][0]+':').c_str(),languages[i][2].c_str());
			for(j=3; j<languages[i].size(); j++) printf(", %s",languages[i][j].c_str());
			printf("\n");
		}
	}
	printf(
"The default for LANGUAGE is the extension of FILENAME. For example,\n"
"'B.java' will indicate a Java solution.\n"
"\n"
"Set URL to the base address of the webinterface without the 'api/' suffix.\n");
	if ( !baseurl.empty() ) {
		printf("The pre-configured URL is '%s'.\n",baseurl.c_str());
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

bool file_istext(char *filename)
{
	magic_t cookie;
	const char *filetype;
	bool res;

	if ( (cookie = magic_open(MAGIC_MIME))==NULL ) goto magicerror;

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
 * Make an API call 'funcname'. If failonerror is false, then a NULL
 * value is returned when the call fails; if true, then error() is called.
 */
Json::Value doAPIrequest(const char *funcname, int failonerror = 1)
{
	CURL *handle;
	CURLcode res;
	char curlerrormsg[CURL_ERROR_SIZE];
	char *url;
	stringstream curloutput;
	Json::Reader reader;
	Json::Value result;

	url = strdup((baseurl+"api/"+API_VERSION+string(funcname)).c_str());

	curlerrormsg[0] = 0;

	handle = curl_easy_init();
	if ( handle == NULL ) {
		free(url);
		if ( failonerror ) {
			error(0,"curl_easy_init() error");
		} else {
			warning(0,"curl_easy_init() error");
		}
		return result;
	}

/* helper macros to easily set curl options */
#define curlsetopt(opt,val) \
	if ( curl_easy_setopt(handle, CURLOPT_ ## opt, val)!=CURLE_OK ) { \
		curl_easy_cleanup(handle); \
		free(url); \
		if ( failonerror ) { \
			error(0,"setting curl option '" #opt "': %s, aborting",curlerrormsg); \
		} else { \
			warning(0,"setting curl option '" #opt "': %s, aborting",curlerrormsg); \
		} \
		return result; }

	/* Set options for post */
	curlsetopt(ERRORBUFFER,   curlerrormsg);
	curlsetopt(FAILONERROR,   failonerror);
	curlsetopt(FOLLOWLOCATION,1);
	curlsetopt(MAXREDIRS,     10);
	curlsetopt(TIMEOUT,       timeout_secs);
	curlsetopt(URL,           url);
	curlsetopt(WRITEFUNCTION, writesstream);
	curlsetopt(WRITEDATA,     (void *)&curloutput);
	curlsetopt(USERAGENT,     DOMJUDGE_PROGRAM " (" PROGRAM " using cURL)");

	if ( verbose >= LOG_DEBUG ) {
		curlsetopt(VERBOSE,   1);
	} else {
		curlsetopt(NOPROGRESS,1);
	}

	logmsg(LOG_INFO,"connecting to %s",url);

	if ( (res=curl_easy_perform(handle))!=CURLE_OK ) {
		curl_easy_cleanup(handle);
		if ( failonerror ) {
			error(0,"downloading '%s': %s",url,curlerrormsg);
		} else {
			warning(0,"downloading '%s': %s",url,curlerrormsg);
		}
		free(url);
		return result;
	}

#undef curlsetopt

	curl_easy_cleanup(handle);

	free(url);

	logmsg(LOG_DEBUG,"API call '%s' returned:\n%s\n",funcname,curloutput.str().c_str());

	if ( !reader.parse(curloutput, result) ) {
		if ( failonerror ) {
			error(0,"parsing REST API output: %s",
			      reader.getFormattedErrorMessages().c_str());
		} else {
			warning(0,"parsing REST API output: %s",
			        reader.getFormattedErrorMessages().c_str());
		}
	}

	return result;
}

bool readlanguages()
{
	Json::Value langs, exts;

	langs = doAPIrequest("languages", 0);

	if ( langs.isNull() ) return false;

	for(Json::ArrayIndex i=0; i<langs.size(); i++) {
		vector<string> lang;

		lang.push_back(langs[i]["name"].asString());
		lang.push_back(langs[i]["require_entry_point"].asString());
		if ( lang[0]=="" ||
		     !(exts = langs[i]["extensions"]) ||
		     !exts.isArray() || exts.size()==0 ) {
			warning(0,"REST API returned unexpected JSON data for languages");
			return false;
		}

		for(Json::ArrayIndex j=0; j<exts.size(); j++) lang.push_back(exts[j].asString());

		languages.push_back(lang);
	}

	return true;
}

bool readcontests()
{
	Json::Value res;

	res = doAPIrequest("contests", 0);

	if ( res.isNull() || !res.isArray() ) return false;

	for(Json::ArrayIndex i=0; i<res.size(); i++) {
		vector<string> contest;

		contest.push_back(res[i]["shortname"].asString());
		contest.push_back(res[i]["name"].asString());
		if ( contest[0]=="" || contest[1]=="" ) {
			warning(0,"REST API returned unexpected JSON data for contests");
			return false;
		}

		contests.push_back(contest);
	}

	return true;
}

bool websubmit()
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

	url = strdup((baseurl+"api/"+API_VERSION+"submissions").c_str());

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
		return false; }
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
	if ( !contest.empty() ) {
		curlformadd(COPYNAME,"contest", COPYCONTENTS,contest.c_str());
	}
	if ( !entry_point.empty() ) {
		curlformadd(COPYNAME,"entry_point", COPYCONTENTS,entry_point.c_str());
	}

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

	logmsg(LOG_DEBUG,"API call 'submissions' returned:\n%s\n",curloutput.str().c_str());

	// The connection worked, but we may have received an HTTP error
	curl_easy_getinfo(handle, CURLINFO_RESPONSE_CODE, &http_code);
	if ( http_code >= 300 ) {
		while ( getline(curloutput,line) ) {
			printf("%s\n", decode_HTML_entities(line).c_str());
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

	return true;
}

//  vim:ts=4:sw=4:
