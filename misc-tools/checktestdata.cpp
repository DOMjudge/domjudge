/*
   Checktestdata -- check testdata according to specification.
   Copyright (C) 2008 Jan Kuipers
   Copyright (C) 2009-2010 Jaap Eldering (eldering@a-eskwadraat.nl).

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

   integer  := 0|-?[1-9][0-9]*
   float    := -?[0-9]+(\.[0-9]+)?([eE][+-]?[0-9]+)?
   variable := [a-z][a-z0-9]*
   value    := <integer> | <float> | <variable>
   compare  := '<' | '>' | '<=' | '>=' | '==' | '!='
   expr     := <term> | <expr> [+-] <term>
   term     := <value> | '-' <term> | '(' <expr> ')' | <term> [*%/] <term>
   test     := '!' <test> | '(' <test> ')' | <test> [&|] <test> |
               <expr> <compare> <expr> | 'MATCH(' <string> str ')' | 'ISEOF'

      MATCH and ISEOF are special keyword that return whether the next
      character matches any of 'str', respectively if end-of-file has
      been reached.

      A value or expression can either be an integer or a floating
      point number. An expression is integer if all its sub-expressions
      are integer.

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

      Matches end-of-file. This is implicitly added at the end of each
      program and must match exactly: no extra data may be present.

   INT(<expr> min, <expr> max [, <variable> name])

      Match an arbitrary sized integer value in the interval [min,max]
      and optionally assign the value read to variable 'name'.

   FLOAT(<expr> min, <expr> max [, <variable> name [, option])

      Match a floating point number in the range [min,max] and
      optionally assign the value read to the variable 'name'.
      When the option 'FIXED' or 'SCIENTIFIC' is set, only accept
      floating point numbers in fixed point and scientific notation,
      respectively.

   STRING(<string> str)

      Match the literal string 'str'.

   REGEX(<string> str)

      Match the extended regular expression 'str'. Matching is
      performed greedily.

   REP(<expr> count [,<command> separator]) [<command>...] END

      Repeat the commands between the 'REP() ... END' statements count
      times and optionally match 'separator' command (count-1) times
      in between. The value of count must fit in a unsigned 32 bit int.

   WHILE(<test> condition [,<command> separator]) [<command>...] END

      Repeat the commands as long as 'condition' is true. Optionally
      match 'separator' command between two consecutive iterations.

 */

#include "../etc/config.h"

#include <iostream>
#include <iomanip>
#include <fstream>
#include <sstream>
#include <vector>
#include <string>
#include <map>
#include <set>
#include <ctype.h>
#include <getopt.h>
#include <stdarg.h>
#ifdef HAVE_BOOST_REGEX
#include <boost/regex.hpp>
#else
#error "Libboost regex library not available."
#endif
#ifdef HAVE_GMPXX_H
#include <gmpxx.h>
#else
#error "LibGMP C++ extensions not available."
#endif

#include "parser.h"

using namespace std;

#define PROGRAM "checktestdata"
#define AUTHORS "Jan Kuipers, Jaap Eldering"
#define VERSION "$Rev$"

enum value_type { value_none, value_int, value_flt };

struct value_t {
	value_type type;
	mpz_class intval;
	mpf_class fltval;

	value_t(): type(value_none) {};
	explicit value_t(mpz_class x): type(value_int), intval(x) {};
	explicit value_t(mpf_class x): type(value_flt), fltval(x) {};

	operator mpz_class() const;
	operator mpf_class() const;
};

ostream& operator <<(ostream &os, const value_t &val)
{
	switch ( val.type ) {
	case value_int: return os << val.intval;
	case value_flt: return os << val.fltval;
	default:        return os << "<no value>";
	}
}

const int exit_testdata = 1;
const int exit_failure  = 2;

const int display_before_error = 65;
const int display_after_error  = 10;

size_t prognr, datanr, linenr, charnr, extra_ws;
command currcmd;

string data;
vector<command> program;
map<string,value_t> variable;
set<string> loop_cmds;
// List of loop starting commands like REP, initialized in main().

char *progname;
char *progfile;
char *datafile;

int whitespace_ok;
int debugging;
int quiet;
int show_help;
int show_version;

struct option const long_opts[] = {
	{"whitespace-ok", no_argument, NULL,         'w'},
	{"debug",   no_argument,       NULL,         'd'},
	{"quiet",   no_argument,       NULL,         'q'},
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
"  -w, --whitespace-ok  whitespace changes are accepted, including heading\n"
"                         and trailing whitespace, but not newlines;\n"
"                         be careful: extra whitespace matches greedily!\n"
"  -d, --debug          enable extra debugging output\n"
"  -q, --quiet          don't display testdata error messages: test exitcode\n"
"      --help           display this help and exit\n"
"      --version        output version information and exit\n"
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
		if ( program[i].name()=="WHILE" ||
		     program[i].name()=="REP" ) replevel++;
		if ( program[i].name()=="END" ) replevel--;
		if ( replevel<0 ) {
			cerr << "unbalanced WHILE/REP/END statements" << endl;
			exit(exit_failure);
		}
	}
	if ( replevel!=0 ) {
		cerr << "unbalanced WHILE/REP/END statements" << endl;
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

void error(string msg = string())
{
	size_t fr = max(0,int(datanr)-display_before_error);
	size_t to = min(data.size(),datanr+display_after_error);

	debug("error at datanr = %d, %d - %d\n",(int)datanr,(int)fr,(int)to);

	if ( !quiet ) {
		cout << data.substr(fr,to-fr) << endl;
		cout << string(min(charnr,(size_t)display_before_error),' ') << "^" << endl << endl;

		cout << "ERROR: line " << linenr+1 << " character " << charnr+1;
		cout << " of testdata doesn't match " << currcmd;
		if ( msg.length()>0 ) cout << ": " << msg;
		cout << endl << endl;
	}

	exit(exit_testdata);
}

value_t::operator mpz_class() const
{
	if ( type!=value_int ) {
		if ( type==value_flt  ) cerr << "float: " << fltval << endl;
		if ( type==value_none ) cerr << "none" << endl;
		cerr << "integer value expected in " << program[prognr] << endl;
		exit(exit_failure);
	}
	return intval;
}

value_t::operator mpf_class() const
{
	if ( !(type==value_int || type==value_flt) ) {
		cerr << "float (or integer) value expected in " << program[prognr] << endl;
		exit(exit_failure);
	}
	if ( type==value_int ) return intval;
	return fltval;
}

value_t value(string x)
{
	debug("value '%s'",x.c_str());
	if ( isalpha(x[0]) ) {
		if ( variable.count(x) ) return variable[x];
		cerr << "variable " << x << " undefined in " << program[prognr] << endl;
		exit(exit_failure);
	}

	value_t res;
	if ( res.intval.set_str(x,0)==0 ) res.type = value_int;
	else if ( res.fltval.set_str(x,0)==0 ) {
		res.type = value_flt;
		// Set sufficient precision:
		if ( res.fltval.get_prec()<4*x.length() ) {
			res.fltval.set_prec(4*x.length());
			res.fltval.set_str(x,0);
		}
	}
	return res;
}

/* We define overloaded arithmetic and comparison operators.
 * As they are all identical, the code is captured in two macro's
 * below, except for the modulus and unary minus.
 */
#define DECL_VALUE_BINOP(op) \
value_t operator op(const value_t &x, const value_t &y) \
{ \
	if ( x.type==value_none || y.type==value_none ) return value_t(); \
	if ( x.type==value_int  && y.type==value_int ) { \
		return value_t(mpz_class(x.intval op y.intval)); \
	} else { \
		return value_t(mpf_class(mpf_class(x) op mpf_class(y))); \
	} \
}

#define DECL_VALUE_CMPOP(op) \
bool operator op(const value_t &x, const value_t &y) \
{ \
	if ( x.type==value_none || y.type==value_none ) return false; \
	if ( x.type==value_int  && y.type==value_int ) return x.intval op y.intval; \
	return mpf_class(x) op mpf_class(y); \
}

DECL_VALUE_BINOP(+)
DECL_VALUE_BINOP(-)
DECL_VALUE_BINOP(*)
DECL_VALUE_BINOP(/)

DECL_VALUE_CMPOP(>)
DECL_VALUE_CMPOP(<)
DECL_VALUE_CMPOP(>=)
DECL_VALUE_CMPOP(<=)
DECL_VALUE_CMPOP(==)
DECL_VALUE_CMPOP(!=)

value_t operator -(const value_t &x)
{
	if ( x.type==value_int ) return value_t(mpz_class(-x.intval));
	if ( x.type==value_flt ) return value_t(mpf_class(-x.fltval));
	return value_t();
}

value_t operator %(const value_t &x, const value_t &y)
{
	if ( x.type==value_none || y.type==value_none ) return value_t();
	value_t res;
	if ( x.type==value_int  && y.type==value_int ) {
		res = x;
		res.intval %= y.intval;
		return res;
	}
	cerr << "cannot use modulo on floats in " << program[prognr] << endl;
	exit(exit_failure);
}

value_t eval(expr e)
{
	debug("eval op='%c', val='%s', #args=%d",e.op,e.val.c_str(),(int)e.args.size());
	switch ( e.op ) {
	case ' ': return value(e.val);
	case 'n': return -eval(e.args[0]);
	case '+': return eval(e.args[0]) + eval(e.args[1]);
	case '-': return eval(e.args[0]) - eval(e.args[1]);
	case '*': return eval(e.args[0]) * eval(e.args[1]);
	case '/': return eval(e.args[0]) / eval(e.args[1]);
	case '%': return eval(e.args[0]) % eval(e.args[1]);
	default:
		cerr << "unknown arithmetic operator " << e.op << " in "
		     << program[prognr] << endl;
		exit(exit_failure);
	}
}

bool compare(args_t cmp)
{
	string op = cmp[0].val;
	value_t l = eval(cmp[1]);
	value_t r = eval(cmp[2]);

	if ( op=="<"  ) return l<r;
	if ( op==">"  ) return l>r;
	if ( op=="<=" ) return l<=r;
	if ( op==">=" ) return l>=r;
	if ( op=="==" ) return l==r;
	if ( op=="!=" ) return l!=r;

	cerr << "unknown compare operator " << op << " in "
	     << program[prognr] << endl;
	exit(exit_failure);
}

bool dotest(test t)
{
	debug("test op='%c', #args=%d",t.op,(int)t.args.size());
	switch ( t.op ) {
	case '!': return !dotest(t.args[0]);
	case '&': return dotest(t.args[0]) && dotest(t.args[1]);
	case '|': return dotest(t.args[0]) || dotest(t.args[1]);
	case 'E': return datanr>=data.size();
	case 'M': return datanr<data.size() && t.args[0].val.find(data[datanr])!=string::npos;
	case '?': return compare(t.args);
	default:
		cerr << "unknown test " << t.op << " in " << program[prognr] << endl;
		exit(exit_failure);
	}
}

int isspace_notnewline(char c) { return isspace(c) && c!='\n'; }

void readwhitespace()
{
	while ( datanr<data.size() && isspace_notnewline(data[datanr]) ) {
		datanr++;
		charnr++;
		extra_ws++;
	}
}

void checkspace()
{
	if ( datanr>=data.size() ) error("end of file");

	if ( whitespace_ok ) {
		// First check at least one space-like character
		if ( !isspace_notnewline(data[datanr++]) ) error();
		charnr++;
		// Then greedily read non-newline whitespace
		readwhitespace();
	} else {
		if ( data[datanr++]!=' ' ) error();
		charnr++;
	}
}

void checknewline()
{
	// Trailing whitespace before newline
	if ( whitespace_ok ) readwhitespace();

	if ( datanr>=data.size() ) error("end of file");
	if ( data[datanr++]!='\n' ) error();
	linenr++;
	charnr=0;

	// Leading whitespace after newline
	if ( whitespace_ok ) readwhitespace();

}

void checktoken(command cmd)
{
	currcmd = cmd;
	debug("checking token %s at %d,%d",cmd.name().c_str(),linenr,charnr);

	if ( cmd.name()=="SPACE" ) checkspace();

	else if ( cmd.name()=="NEWLINE" ) checknewline();

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

//		debug("%s <= %s <= %s",lo.get_str().c_str(),num.c_str(),hi.get_str().c_str());
		if ( cmd.nargs()>=3 ) debug("'%s' = '%s'",cmd.args[2].c_str(),num.c_str());

		if ( num.size()==0 ) error();
		if ( num.size()>=2 && num[0]=='0' ) error("prefix zero(s)");
		if ( num.size()>=1 && num[0]=='-' &&
		     (num.size()==1 || num[1]=='0') ) error("invalid minus sign (-0 not allowed)");

		mpz_class x(num);

		if ( x<lo || x>hi ) error("value out of range");
		if ( cmd.nargs()>=3 ) variable[cmd.args[2]] = value_t(x);

		datanr += len;
		charnr += len;
	}

	else if ( cmd.name()=="FLOAT" ) {
		// Accepts format -?[0-9]+(\.[0-9]+)?([eE][+-]?[0-9]+)?
		// where the last optional part, the exponent, is not allowed
		// with the FIXED option and required with the SCIENTIFIC option.

		string float_regex("-?[0-9]+(\\.[0-9]+)?([eE][+-]?[0-9]+)?");
		string fixed_regex("-?[0-9]+(\\.[0-9]+)?");
		string scien_regex("-?[0-9]+(\\.[0-9]+)?[eE][+-]?[0-9]+");
		boost::regex regexstr(float_regex);
		boost::match_results<string::const_iterator> res;
		boost::match_flag_type flags = boost::match_default | boost::match_continuous;
		string matchstr;

		if ( cmd.nargs()>=4 ) {
			if ( cmd.args[3].name()=="SCIENTIFIC" ) regexstr = scien_regex;
			else if ( cmd.args[3].name()=="FIXED" ) regexstr = fixed_regex;
			else {
				cerr << "invalid option in " << program[prognr] << endl;
				exit(exit_failure);
			}
		}

		if ( !boost::regex_search((string::const_iterator)&data[datanr],
		                          (string::const_iterator)data.end(),
		                          res,regexstr,flags) ) {
			error();
		}
		size_t matchend = size_t(res[0].second-data.begin());
		matchstr = string(data.begin()+datanr,data.begin()+matchend);

		mpf_class x(matchstr,4*matchstr.length());

		mpf_class lo = eval(cmd.args[0]);
		mpf_class hi = eval(cmd.args[1]);

		if ( x<lo || x>hi ) error("value out of range");
		if ( cmd.nargs()>=3 ) variable[cmd.args[2]] = value_t(x);

		charnr += matchend - datanr;
		datanr = matchend;
	}

	else if ( cmd.name()=="STRING" ) {
		string str = cmd.args[0];
		for (size_t i=0; i<str.size(); i++) {
			if ( datanr>=data.size() ) error("premature end of file");
			if ( data[datanr++]!=str[i] ) error();
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

		else if ( cmd.name()=="REP" ||
		          cmd.name()=="WHILE" ) {

			// Current and maximum loop iterations.
			unsigned long long i = 0, times = ULLONG_MAX;

			if ( cmd.name()=="REP" ) {
				mpz_class n = eval(cmd.args[0]);
				if ( !n.fits_ulong_p() ) {
					cerr << "'" << n << "' does not fit in an unsigned long in "
						 << program[prognr] << endl;
					exit(exit_failure);
				}
				times = n.get_ui();
			}

			// Begin and end of loop commands
			int loopbegin, loopend;

			loopbegin = loopend = prognr + 1;

			for(int looplevel=1; looplevel>0; ++loopend) {
				string cmdstr = program[loopend].name();
				if ( loop_cmds.count(cmdstr) ) looplevel++;
				if ( cmdstr=="END" ) looplevel--;
			}

			// Run loop...
			debug("running %s loop, commands %d - %d, max. times = %lld",
			      cmd.name().c_str(),loopbegin,loopend,times);

			while ( (cmd.name()=="REP"   && i<times) ||
			        (cmd.name()=="WHILE" && dotest(cmd.args[0])) ) {

				debug("loop iteration %lld/%lld",i+1,times);
				prognr = loopbegin;
				if ( i>0 && cmd.nargs()>=2 ) checktoken(cmd.args[1]);
				checktestdata();
				i++;
			}

			// And skip to end of loop
			prognr = loopend;
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
	whitespace_ok = 0;
	debugging = quiet = show_help = show_version = 0;
	opterr = 0;
	while ( (opt = getopt_long(argc,argv,"+wdq",long_opts,(int *) 0))!=-1 ) {
		switch ( opt ) {
		case 0:   /* long-only option */
			break;
		case 'w':
			whitespace_ok = 1;
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

	// Output floats with high precision:
	cout << setprecision(50);
	cerr << setprecision(50);
	mpf_set_default_prec(256);

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

	if ( debugging ) {
		for(size_t i=0; i<program.size(); i++) cerr << program[i] << endl;
	}

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
	extra_ws = 0;

	// Initialize loop_cmds here, as a set cannot be initialized on
	// declaration.
	loop_cmds.insert("REP");
	loop_cmds.insert("WHILE");

	// If we ignore whitespace, skip leading whitespace on first line
	// as a special case; other lines are handled by checknewline().
	if ( whitespace_ok ) readwhitespace();

	checktestdata();

	if ( !quiet ) cout << "testdata ok!" << endl;

	return 0;
}
