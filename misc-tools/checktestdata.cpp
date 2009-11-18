/*
   Checktestdata -- check testdata according to specification.
   Copyright (C) 2008 Jan Kuipers
   Copyright (C) 2009 Jaap Eldering (eldering@a-eskwadraat.nl).

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


   Grammar and command syntax below. All commands are uppercase, while
   variables are lowercase with non-leading digits. Lines starting
   with '#' are comments and ignored.

   integer  := [0-9]+
   variable := [a-z][a-z0-9]*
   value    := <integer> | <variable>
   expr     := <term> | <expr> [+-] <term>
   term     := <value> | '-' <term> | '(' <expr> ')' | <term> [*%/] <term>

   string   := ".*"

      Within a string, the backslash acts as escape character for the
      following expressions:

      * \[0-7]{1,3} denotes an octal escape for a character
      * \n, \t, \r, \b denote linefeed, tab, carriage return and backspace
      * \" and \\ denote " and \
      * an escaped newline is ignored (line continuation)

      A backslash preceding any other character is treated as a
      literal backslash.

   command  :=

   SPACE / NEWLINE

      No-argument commands matching a single space (0x20) or newline
      respectively.

   EOF

      Matches end-of-file. This implicitly added at the end of each
      program and must match exactly: no extra data may be present.

   INT(<expr> min, <expr> max [, <variable> name])

      Match an arbitrary sized integer value in the interval [min,max]
      and optionally assign the value read to variable 'name'.

   STRING(<string> str)

      Match the literal string 'str'.

   REGEX(<string> str)

      Match the extended regular expression 'str'. Matching is
      performed greedily.

   REP(<expr> count [,<command> separator]) [<command>...] END

      Repeat the commands between the 'REP() ... END' statements count
      times and optionally match 'separator' command (count-1) times
      in between. The value of count must fit in a unsigned 32 bit int.

 */

#include <iostream>
#include <fstream>
#include <sstream>
#include <vector>
#include <string>
#include <map>
#include <ctype.h>
#include <getopt.h>
#include <stdarg.h>
#include <boost/regex.hpp>
#include <gmpxx.h>

#include "parser.h"

using namespace std;

#define PROGRAM "checktestdata"
#define AUTHORS "Jan Kuipers, Jaap Eldering"
#define VERSION "$Rev$"

const int exit_testdata = 1;
const int exit_failure  = 2;

const int display_before_error = 65;
const int display_after_error  = 10;

size_t prognr, datanr, linenr, charnr;
command currcmd;

string data;
vector<command> program;
map<string,mpz_class> variable;

char *progname;
char *progfile;
char *datafile;

int debugging;
int quiet;
int show_help;
int show_version;

struct option const long_opts[] = {
	{"debug",   no_argument,       NULL,         'd'},
	{"help",    no_argument,       &show_help,    1 },
	{"version", no_argument,       &show_version, 1 },
	{ NULL,     0,                 NULL,          0 }
};

void version()
{
        printf("%s -- written by %s\n",PROGRAM,AUTHORS);
        printf("Version %s, included with DOMjudge.\n\n",VERSION);
        printf(
"%s comes with ABSOLUTELY NO WARRANTY.  This is free software, and you\n"
"are welcome to redistribute it under certain conditions.  See the GNU\n"
"General Public Licence for details.\n",PROGRAM);
}

void usage()
{
        printf(
"Usage: %s [OPTION]... PROGRAM [TESTDATA]\n"
"Check TESTDATA according to specification in PROGRAM file.\n"
"If TESTDATA is '-' or not specified, read from stdin.\n"
"\n"
"  -d, --debug        enable extra debugging output\n"
"  -q, --quiet        don't display testdata error messages: test exitcode\n"
"      --help         display this help and exit\n"
"      --version      output version information and exit\n"
"\n",progname);
}

void debug(const char *format, ...)
{
	va_list ap;
	va_start(ap,format);

	if ( debugging ) {
		fprintf(stderr,"debug: ");

        if ( format!=NULL ) {
			vfprintf(stderr,format,ap);
        } else {
			fprintf(stderr,"<no debug data?>");
        }

		fprintf(stderr,"\n");
	}

	va_end(ap);
}

void readprogram(istream &in)
{
	debug("parsing script...");

	Parser parseprog(in);
	if ( parseprog.parse()!=0 ) {
		cerr << "parse error reading " << progfile << endl;
		exit(exit_failure);
	}

	// Add (implicit) EOF command at end of input
	program.push_back(command("EOF"));

	// Check for correct REP ... END nesting
	int replevel = 0;
	for (size_t i=0; i<program.size(); i++) {
		if ( program[i].name()=="REP" ) replevel++;
		if ( program[i].name()=="END" ) replevel--;
		if ( replevel<0 ) {
			cerr << "unbalanced REP/END statements" << endl;
			exit(exit_failure);
		}
	}
	if ( replevel!=0 ) {
		cerr << "unbalanced REP/END statements" << endl;
		exit(exit_failure);
	}
}

void readtestdata(istream &in)
{
	debug("reading testdata...");

	stringstream ss;
	ss << in.rdbuf();
	if ( in.fail() ) {
		cerr << "error reading " << datafile << endl;
		exit(exit_failure);
	}

 	data = ss.str();
}

void error()
{
	size_t fr = max(0,int(datanr)-display_before_error);
	size_t to = min(data.size(),datanr+display_after_error);

	debug("error at datanr = %d, %d - %d\n",(int)datanr,(int)fr,(int)to);

	if ( !quiet ) {
		cout << data.substr(fr,to-fr) << endl;
		cout << string(min(charnr,(size_t)display_before_error),' ') << "^" << endl << endl;

		cout << "ERROR: line " << linenr << " character " << charnr;
		cout << " of testdata doesn't match " << currcmd << endl << endl;
	}

	exit(exit_testdata);
}

mpz_class value(string x)
{
	debug("value '%s'",x.c_str());
	if ( isalpha(x[0]) ) {
		if ( variable.count(x) ) return variable[x];
		cerr << "variable " << x << " undefined in " << program[prognr] << endl;
		exit(exit_failure);
	}

	return mpz_class(x);
}

mpz_class eval(expr e)
{
	debug("eval op='%c', val='%s', #args=%d",e.op,e.val.c_str(),(int)e.args.size());
	switch ( e.op ) {
	case 'n': return -eval(e.args[0]);
	case '(': return eval(e.args[0]);
	case '+': return eval(e.args[0]) + eval(e.args[1]);
	case '-': return eval(e.args[0]) - eval(e.args[1]);
	case '*': return eval(e.args[0]) * eval(e.args[1]);
	case '/': return eval(e.args[0]) / eval(e.args[1]);
	case '%': return eval(e.args[0]) % eval(e.args[1]);
	default:  return value(e.val);
	}
}

void checktoken(command cmd)
{
	currcmd = cmd;
	debug("checking token %s at %d,%d",cmd.name().c_str(),linenr,charnr);

	if ( cmd.name()=="SPACE" ) {
		if ( datanr>=data.size() || data[datanr++]!=' ' ) error();
		charnr++;
	}

	else if ( cmd.name()=="NEWLINE" ) {
		if ( datanr>=data.size() || data[datanr++]!='\n' ) error();
		linenr++;
		charnr=0;
	}

	else if ( cmd.name()=="INT" ) {
		// Accepts format (0|-?[1-9][0-9]*), i.e. no leading zero's
		// and no '-0' accepted.
		string num;
		size_t len = 0;
		while ( datanr<data.size() &&
		        (isdigit(data[datanr+len]) ||
		         (num.size()==0 && data[datanr+len]=='-')) ) {
			num += data[datanr+len];
			len++;
		}

		mpz_class lo = eval(cmd.args[0]);
		mpz_class hi = eval(cmd.args[1]);

		debug("%s <= %s <= %s",lo.get_str().c_str(),num.c_str(),hi.get_str().c_str());
		if ( cmd.nargs()>=3 ) debug("'%s' = '%s'",cmd.args[2].c_str(),num.c_str());

		if ( num.size()==0 ) error();
		if ( num.size()>=2 && num[0]=='0' ) error();
		if ( num.size()>=1 && num[0]=='-' &&
		     (num.size()==1 || num[1]=='0') ) error();

		mpz_class n(num);

		if ( n<lo || n>hi ) error();
		if ( cmd.nargs()>=3 ) variable[cmd.args[2]] = n;

		datanr += len;
		charnr += len;
	}

	else if ( cmd.name()=="STRING" ) {
		string str = cmd.args[0];
		for (size_t i=0; i<str.size(); i++) {
			if ( datanr>=data.size() || data[datanr++]!=str[i] ) error();
			charnr++;
			if ( str[i]=='\n' ) linenr++, charnr=0;
		}

		debug("'%s' = '%s'",str.c_str(),cmd.args[0].c_str());
	}

	else if ( cmd.name()=="REGEX" ) {
		boost::regex regexstr(string(cmd.args[0]));
		boost::match_results<string::const_iterator> res;
		boost::match_flag_type flags = boost::match_default | boost::match_continuous;
		string matchstr;

		if ( !boost::regex_search((string::const_iterator)&data[datanr],
		                          (string::const_iterator)data.end(),
								  res,regexstr,flags) ) {
			error();
		} else {
			size_t matchend = size_t(res[0].second-data.begin());
			matchstr = string(data.begin()+datanr,data.begin()+matchend);
			for (; datanr<matchend; datanr++) {
				charnr++;
				if ( data[datanr]=='\n' ) linenr++, charnr=0;
			}
		}

		debug("'%s' = '%s'",matchstr.c_str(),cmd.args[0].c_str());
	}

	else {
		cerr << "unknown command " << program[prognr] << endl;
		exit(exit_failure);
	}
}

void checktestdata()
{
	while ( true ) {
		command cmd = currcmd = program[prognr];

		if ( cmd.name()=="EOF" ) {
			if ( datanr++!=data.size() ) error();
			return;
		}

		else if ( cmd.name()=="REP" ) {
			mpz_class n = eval(cmd.args[0]);
			if ( !n.fits_ulong_p() ) {
				cerr << "'" << n << "' does not fit in unsigned long in "
				     << program[prognr] << endl;
				exit(exit_failure);
			}
			long long times = n.get_ui();

			if ( times==0 ) {
				int replevel = 0;
				do {
					command cmd = program[prognr++];
					if ( cmd.name()=="REP" ) replevel++;
					if ( cmd.name()=="END" ) replevel--;
				}
				while ( replevel>0 );
			}
			else {
				int loopstart = prognr+1;

				while ( times-- ) {
					debug("REP times = %lld",times+1);
					prognr = loopstart;
					checktestdata();
					if ( times && cmd.nargs()>=2 ) checktoken(cmd.args[1]);
				}
			}
		}

		else if ( cmd.name()=="END" ) {
			prognr++;
			return;
		}

		else {
			checktoken(cmd);
			prognr++;
		}
	}
}

int main(int argc, char **argv)
{
	int opt;

	progname = argv[0];

	/* Parse command-line options */
	debugging = quiet = show_help = show_version = 0;
	opterr = 0;
	while ( (opt = getopt_long(argc,argv,"+dq",long_opts,(int *) 0))!=-1 ) {
		switch ( opt ) {
		case 0:   /* long-only option */
			break;
		case 'd':
			debugging = 1;
			break;
		case 'q':
			quiet = 1;
			break;
		case ':': /* getopt error */
		case '?':
			printf("unknown option or missing argument `%c'",optopt);
			exit(exit_failure);
		default:
			printf("getopt returned character code `%c' ??",(char)opt);
			exit(exit_failure);
		}
	}

	if ( show_help    ) { usage();   return 0; }
	if ( show_version ) { version(); return 0; }

	debug("debugging enabled");

	if ( argc<=optind ) {
		printf("Error: no PROGRAM file specified.\n");
		usage();
		return exit_failure;
	}
	progfile = argv[optind];
	ifstream prog(progfile);
	if ( prog.fail() ) {
		cerr << "error opening " << progfile << endl;
		exit(exit_failure);
	}
	readprogram(prog);
	prog.close();

	if ( argc<=optind+1 ) {
		datafile = strdup("-");
	} else {
		datafile = argv[optind+1];
	}

	if ( strcmp(datafile,"-")==0 ) {
		// No TESTDATA file specified, read from stdin
		readtestdata(cin);
	} else {
		ifstream fin(datafile);
		if ( fin.fail() ) {
			cerr << "error opening " << datafile << endl;
			exit(exit_failure);
		}
		readtestdata(fin);
		fin.close();
	}

	linenr = charnr = 0;
	datanr = prognr = 0;

	checktestdata();

	if ( !quiet ) cout << "testdata ok!" << endl;

	return 0;
}
