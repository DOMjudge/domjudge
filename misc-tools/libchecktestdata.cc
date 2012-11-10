/*
   Libchecktestdata -- check testdata according to specification.
   Copyright (C) 2008-2012 Jan Kuipers
   Copyright (C) 2009-2012 Jaap Eldering (eldering@a-eskwadraat.nl).
   Copyright (C) 2012 Tobias Werth (werth@cs.fau.de)

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

#include "config.h"

#include <iostream>
#include <iomanip>
#include <fstream>
#include <sstream>
#include <vector>
#include <string>
#include <map>
#include <set>
#include <cctype>
#include <cstdarg>
#include <climits>
#include <getopt.h>
#include <sys/time.h>
#include <cstdlib>
#ifdef HAVE_BOOST_REGEX
#include <boost/regex.hpp>
#include <boost/lexical_cast.hpp>
#else
#error "Libboost regex library not available."
#endif
#ifdef HAVE_GMPXX_H
#include <gmpxx.h>
#else
#error "LibGMP C++ extensions not available."
#endif

#include "parser.h"
#include "libchecktestdata.h"

using namespace std;

#define PROGRAM "checktestdata"
#define AUTHORS "Jan Kuipers, Jaap Eldering, Tobias Werth"
#define VERSION DOMJUDGE_VERSION "/" REVISION

enum value_type { value_none, value_int, value_flt };

struct value_t {
	value_type type;
	mpz_class intval;
	mpf_class fltval;

	value_t(): type(value_none) {}
	explicit value_t(mpz_class x): type(value_int), intval(x) {}
	explicit value_t(mpf_class x): type(value_flt), fltval(x) {}

	operator mpz_class() const;
	operator mpf_class() const;
};

class doesnt_match_exception {};
class eof_found_exception {};
class generate_exception {};

ostream& operator <<(ostream &os, const value_t &val)
{
	switch ( val.type ) {
	case value_int: return os << val.intval;
	case value_flt: return os << val.fltval;
	default:        return os << "<no value>";
	}
}

const int display_before_error = 65;
const int display_after_error  = 50;

size_t prognr, datanr, linenr, charnr, extra_ws;
command currcmd;

gmp_randclass gmp_rnd(gmp_randinit_default);

string data;
vector<command> program;
map<string,value_t> variable, preset;
set<string> loop_cmds;
// List of loop starting commands like REP, initialized in checksyntax.

int whitespace_ok;
int debugging;
int quiet;
int gendata;

void debug(const char *, ...) __attribute__((format (printf, 1, 2)));

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
		cerr << "parse error reading program" << endl;
		exit(exit_failure);
	}

	// Add (implicit) EOF command at end of input
	program.push_back(command("EOF"));

	// Check for correct REP ... END nesting
	vector<string> levels;
	for (size_t i=0; i<program.size(); i++) {
		if ( loop_cmds.count(program[i].name()) ||
		     program[i].name() == "IF" )
			levels.push_back(program[i].name());

		if ( program[i].name()=="END" ) {
			if ( levels.size()==0 ) {
				cerr << "incorrect END statement" << endl;
				exit(exit_failure);
			}
			levels.pop_back();
		}

		if ( program[i].name()=="ELSE" ) {
			if ( levels.size()==0 || levels.back()!="IF") {
				cerr << "incorrect ELSE statement" << endl;
				exit(exit_failure);
			}
			levels.pop_back();
			levels.push_back(program[i].name());
		}
	}
	if ( levels.size()>0 ) {
		cerr << "unclosed block statement(s)" << endl;
		exit(exit_failure);
	}
}

void readtestdata(istream &in)
{
	debug("reading testdata...");

	stringstream ss;
	ss << in.rdbuf();
	if ( in.fail() ) {
		cerr << "error reading testdata" << endl;
		exit(exit_failure);
	}

 	data = ss.str();
}

void error(string msg = string())
{
	if ( gendata ) {
		cerr << "ERROR: in command " << currcmd << ": " << msg << endl << endl;
		throw generate_exception();
	}

	size_t fr = max(0,int(datanr)-display_before_error);
	size_t to = min(data.size(),datanr+display_after_error);

	debug("error at datanr = %d, %d - %d\n",(int)datanr,(int)fr,(int)to);

	if ( !quiet ) {
		cerr << data.substr(fr,datanr-fr) << endl;
		cerr << string(min(charnr,(size_t)display_before_error),' ') << '^';
		cerr << data.substr(datanr,to-datanr) << endl << endl;

		cerr << "ERROR: line " << linenr+1 << " character " << charnr+1;
		cerr << " of testdata doesn't match " << currcmd;
		if ( msg.length()>0 ) cerr << ": " << msg;
		cerr << endl << endl;
	}

	throw doesnt_match_exception();
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

value_t pow(const value_t &x, const value_t &y)
{
	if ( x.type==value_none || y.type==value_none ) return value_t();
	if ( y.type!=value_int ) {
		cerr << "float exponent not allowed in " << program[prognr] << endl;
		exit(exit_failure);
	}
	if ( !y.intval.fits_ulong_p() ) {
		cerr << "integer exponent " << y.intval
			 << " does not fit in unsigned long in " << program[prognr] << endl;
		exit(exit_failure);
	}
	unsigned long y1 = y.intval.get_ui();
	value_t res;
	if ( x.type==value_int ) {
		res = x;
		mpz_pow_ui(res.intval.get_mpz_t(),x.intval.get_mpz_t(),y1);
		return res;
	}
	mpf_class x1 = x;
	res = value_t(x1);
	mpf_pow_ui(res.fltval.get_mpf_t(),x1.get_mpf_t(),y1);
	return res;
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
	case '^': return pow(eval(e.args[0]),eval(e.args[1]));
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
	case 'E': if ( gendata ) {
			  return (rand() % 10 < 3);
		  } else {
			  return datanr>=data.size();
		  }
	case 'M': if ( gendata ) {
			  return (rand() % 2 == 0);
		  } else {
			  return datanr<data.size() && t.args[0].val.find(data[datanr])!=string::npos;
		  }
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

#define MAX_MULT 10
int getmult(string &exp, unsigned int &index)
{
	index++;

	if (index >= exp.length()) {
		return 1;
	}

	int min = 0;
	int max = MAX_MULT;
	switch (exp[index]) {
	case '?':
		index++;
		max = 1;
		break;
	case '+':
		index++;
		min = 1;
		break;
	case '*':
		index++;
		break;
	case '{':
		index++;
		{
			int end = exp.find_first_of('}', index);
			string minmaxs = exp.substr(index, end - index);
			int pos = minmaxs.find_first_of(',');
			if (pos == -1) {
				min = max = boost::lexical_cast<int>(minmaxs);
			} else {
				string mins = minmaxs.substr(0, pos);
				string maxs = minmaxs.substr(pos + 1);
				min = boost::lexical_cast<int>(mins);
				if (maxs.length() > 0) {
					max = boost::lexical_cast<int>(maxs);
				}
			}
			index = end + 1;
		}
		break;
	default:
		min = 1;
		max = 1;
	}

	return (min + rand() % (1 + max - min));
}

void genregex(string exp, ostream &datastream)
{
	unsigned int i = 0;
	while (i < exp.length()) {
		switch (exp[i]) {
		case '\\':
			{
				i++;
				char c = exp[i];
				int mult = getmult(exp, i);
				for (int cnt = 0; cnt < mult; cnt++) {
					datastream << c;
				}
			}
			break;
		case '.':
			{
				int mult = getmult(exp, i);
				for (int cnt = 0; cnt < mult; cnt++) {
					datastream << (char) (' ' + (rand() % (int) ('~' - ' ')));
				}
			}
			break;
		case '[':
			{
				set<char> possible;
				bool escaped = false;
				while (i + 1 < exp.length()) {
					i++;
					if (escaped) {
						if (exp[i] == '\\') {
							escaped = false;
						}
					} else if (exp[i] == ']') {
						break;
					} else if (exp[i] == '\\') {
						escaped = true;
						continue;
					}
					if (i + 2 < exp.length() && exp[i + 1] == '-') {
						char from = exp[i];
						i += 2;
						char to = exp[i];
						if (to == '\\') {
							i++;
							to = exp[i];
						}
						while (from <= to) {
							possible.insert(from);
							from++;
						}
					} else {
						possible.insert(exp[i]);
					}
				}
				vector<char> possibleVec;
				copy(possible.begin(), possible.end(), std::back_inserter(possibleVec));
				int mult = getmult(exp, i);
				for (int cnt = 0; cnt < mult; cnt++) {
					datastream << possibleVec[rand() % possibleVec.size()];
				}
			}
			break;
		case '(':
			{
				i++;
				vector<string> alternatives;
				int depth = 0;
				int begin = i;
				bool escaped = false;
				while (depth > 0 || escaped || exp[i] != ')') {
					if (exp[i] == '\\') {
						escaped = !escaped;
					} else if (depth == 0 && exp[i] == '|' && !escaped) {
						alternatives.push_back(exp.substr(begin, i - begin));
						begin = i + 1;
					} else if (exp[i] == '(' && !escaped) {
						depth++;
					} else if (exp[i] == ')' && !escaped) {
						depth--;
					}
					i++;
				}
				alternatives.push_back(exp.substr(begin, i - begin));
				int mult = getmult(exp, i);
				for (int cnt = 0; cnt < mult; cnt++) {
					genregex(alternatives[rand() % alternatives.size()], datastream);
				}
			}
			break;
		default:
			{
				char c = exp[i];
				int mult = getmult(exp, i);
				for (int cnt = 0; cnt < mult; cnt++) {
					datastream << c;
				}
			}
			break;
		}
	}
}

void gentoken(command cmd, ostream &datastream)
{
	currcmd = cmd;
	debug("generating token %s at %lu,%lu",
	      cmd.name().c_str(),(unsigned long)linenr,(unsigned long)charnr);

	if ( cmd.name()=="SPACE" ) datastream << ' ';

	else if ( cmd.name()=="NEWLINE" ) datastream << '\n';

	else if ( cmd.name()=="INT" ) {
		mpz_class lo = eval(cmd.args[0]);
		mpz_class hi = eval(cmd.args[1]);
		mpz_class x(lo + gmp_rnd.get_z_range(hi - lo + 1));

		if ( cmd.nargs()>=3 ) {
			// Check if we have a preset value, then override the
			// random generated value
			if ( preset.count(cmd.args[2]) ) {
				x = preset[cmd.args[2]];
				if ( x<lo || x>hi ) {
					error("preset value for '" + string(cmd.args[2]) + "' out of range");
				}
			}

			variable[cmd.args[2]] = value_t(x);
		}

		datastream << x.get_str();
	}

	else if ( cmd.name()=="FLOAT" ) {
		mpf_class lo = eval(cmd.args[0]);
		mpf_class hi = eval(cmd.args[1]);

		if ( cmd.nargs()>=4 ) {
			if ( cmd.args[3].name()=="SCIENTIFIC" ) datastream << scientific;
			else if ( cmd.args[3].name()=="FIXED" ) datastream << fixed;
			else {
				cerr << "invalid option in " << program[prognr] << endl;
				exit(exit_failure);
			}
		}

		mpf_class x(lo + gmp_rnd.get_f()*(hi-lo));

		if ( cmd.nargs()>=3 ) {
			// Check if we have a preset value, then override the
			// random generated value
			if ( preset.count(cmd.args[2]) ) {
				x = preset[cmd.args[2]];
				if ( x<lo || x>hi ) {
					error("preset value for '" + string(cmd.args[2]) + "' out of range");
				}
			}

			variable[cmd.args[2]] = value_t(x);
		}

		datastream << x;
	}

	else if ( cmd.name()=="STRING" ) {
		string str = cmd.args[0];
		datastream << str;
	}

	else if ( cmd.name()=="REGEX" ) {
		string regex = cmd.args[0];
		boost::regex e1(regex, boost::regex::extended); // this is only to check the expression
		genregex(regex, datastream);
	}

	else if ( cmd.name()=="ASSERT" ) {
		if ( !dotest(cmd.args[0]) ) error("assertion failed");
	}

	else {
		cerr << "unknown command " << program[prognr] << endl;
		exit(exit_failure);
	}
}

void checktoken(command cmd)
{
	currcmd = cmd;
	debug("checking token %s at %lu,%lu",
	      cmd.name().c_str(),(unsigned long)linenr,(unsigned long)charnr);

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
		                          res,regexstr,boost::match_continuous) ) {
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
		string matchstr;

		if ( !boost::regex_search((string::const_iterator)&data[datanr],
		                          (string::const_iterator)data.end(),
								  res,regexstr,boost::match_continuous) ) {
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

	else if ( cmd.name()=="ASSERT" ) {
		if ( !dotest(cmd.args[0]) ) error("assertion failed");
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
			debug("checking EOF");
			if ( datanr++!=data.size() ) error();
			throw eof_found_exception();
		}

		else if ( loop_cmds.count(cmd.name()) ) {

			// Current and maximum loop iterations.
			unsigned long i = 0, times = ULONG_MAX;

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
				if ( loop_cmds.count(cmdstr) || cmdstr=="IF") looplevel++;
				if ( cmdstr=="END" ) looplevel--;
			}

			// Run loop...
			debug("running %s loop, commands %d - %d, max. times = %ld",
			      cmd.name().c_str(),loopbegin,loopend,times);

			while ( (cmd.name()=="REP"   && i<times) ||
			        (cmd.name()=="WHILE" && dotest(cmd.args[0])) ) {

				debug("loop iteration %ld/%ld",i+1,times);
				prognr = loopbegin;
				if ( i>0 && cmd.nargs()>=2 ) checktoken(cmd.args[1]);
				checktestdata();
				i++;
			}

			// And skip to end of loop
			prognr = loopend;
		}

		else if ( cmd.name()=="IF" ) {

			// Find line numbers of matching else/end
			int ifnr   = prognr;
			int elsenr = -1;
			int endnr  = prognr+1;

			for(int looplevel=1; looplevel>0; ++endnr) {
				string cmdstr = program[endnr].name();
				if ( loop_cmds.count(cmdstr) || cmdstr=="IF") looplevel++;
				if ( cmdstr=="END" ) looplevel--;
				if ( cmdstr=="ELSE" && looplevel==1) elsenr = endnr;
			}
			endnr--;

			debug("IF statement, if/else/end commands: %d/%d/%d",
			      ifnr,elsenr,endnr);

			// Test and execute correct command block
			if (dotest(cmd.args[0])) {
				debug("executing IF clause");
				prognr = ifnr+1;
				checktestdata();
			}
			else if (elsenr!=-1) {
				debug("executing ELSE clause");
				prognr = elsenr+1;
				checktestdata();
			}

			prognr = endnr+1;
		}

		else if ( cmd.name()=="END" || cmd.name()=="ELSE" ) {
			debug("scope closed by %s",cmd.name().c_str());
			prognr++;
			return;
		}

		else {
			checktoken(cmd);
			prognr++;
		}
	}
}

void genrandomdata(ostream &datastream) {
	while ( true ) {
		command cmd = currcmd = program[prognr];

		if ( cmd.name()=="EOF" ) {
			debug("we are done ;-)");
			return;
		}

		else if ( loop_cmds.count(cmd.name()) ) {
			// Current and maximum loop iterations.
			unsigned long i = 0, times = ULONG_MAX;

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
				if ( loop_cmds.count(cmdstr) || cmdstr=="IF") looplevel++;
				if ( cmdstr=="END" ) looplevel--;
			}

			// Run loop...
			debug("running %s loop, commands %d - %d, max. times = %ld",
			      cmd.name().c_str(),loopbegin,loopend,times);

			while ( (cmd.name()=="REP"   && i<times) ||
			        (cmd.name()=="WHILE" && dotest(cmd.args[0])) ) {

				debug("loop iteration %ld/%ld",i+1,times);
				prognr = loopbegin;
				if ( i>0 && cmd.nargs()>=2 ) gentoken(cmd.args[1], datastream);
				genrandomdata(datastream);
				i++;
			}

			// And skip to end of loop
			prognr = loopend;
		}

		else if ( cmd.name()=="IF" ) {
			// Find line numbers of matching else/end
			int ifnr   = prognr;
			int elsenr = -1;
			int endnr  = prognr+1;

			for(int looplevel=1; looplevel>0; ++endnr) {
				string cmdstr = program[endnr].name();
				if ( loop_cmds.count(cmdstr) || cmdstr=="IF") looplevel++;
				if ( cmdstr=="END" ) looplevel--;
				if ( cmdstr=="ELSE" && looplevel==1) elsenr = endnr;
			}
			endnr--;

			debug("IF statement, if/else/end commands: %d/%d/%d",
			      ifnr,elsenr,endnr);

			// Test and execute correct command block
			if (dotest(cmd.args[0])) {
				debug("executing IF clause");
				prognr = ifnr+1;
				genrandomdata(datastream);
			}
			else if (elsenr!=-1) {
				debug("executing ELSE clause");
				prognr = elsenr+1;
				genrandomdata(datastream);
			}

			prognr = endnr+1;
		}

		else if ( cmd.name()=="END" || cmd.name()=="ELSE" ) {
			debug("scope closed by %s",cmd.name().c_str());
			prognr++;
			return;
		}

		else {
			gentoken(cmd, datastream);
			prognr++;
		}
	}
}

void init_checktestdata(std::istream &progstream, int opt_mask)
{
	// Output floats with high precision:
	cout << setprecision(10);
	cerr << setprecision(10);
	mpf_set_default_prec(256);

	// Check the options bitmask
	if (opt_mask & opt_whitespace_ok) whitespace_ok = 1;
	if (opt_mask & opt_debugging    ) debugging = 1;
	if (opt_mask & opt_quiet        ) quiet = 1;

	// Initialize block_cmds here, as a set cannot be initialized on
	// declaration.
	loop_cmds.insert("REP");
	loop_cmds.insert("WHILE");

	// Read program and testdata
	readprogram(progstream);

	if ( debugging ) {
		for(size_t i=0; i<program.size(); i++) cerr << program[i] << endl;
	}

	// Initialize random generators
	struct timeval time;
	unsigned long seed;
	gettimeofday(&time,NULL);
	seed = (time.tv_sec * 1000) + (time.tv_usec % 1000);
	srand(seed);
	gmp_rnd.seed(seed);

	// Initialize current position in program and data.
	linenr = charnr = 0;
	datanr = prognr = 0;
	extra_ws = 0;
}

void gentestdata(ostream &datastream)
{
	// Generate random testdata
	gendata = 1;
	genrandomdata(datastream);
}

bool checksyntax(istream &datastream)
{
	gendata = 0;
	readtestdata(datastream);

	// If we ignore whitespace, skip leading whitespace on first line
	// as a special case; other lines are handled by checknewline().
	if ( whitespace_ok ) readwhitespace();

	try {
		checktestdata();
	}
	catch (doesnt_match_exception) {
		return false;
	}
	catch (eof_found_exception) {}

	return true;
}

bool parse_preset_list(std::string list)
{
	size_t pos = 0, sep1, sep2;
	string name, val_str;
	value_t value;

	debug("parsing preset list '%s'", list.c_str());

	while ( pos<list.length() ) {
		if ( ( sep1=list.find('=', pos) )==string::npos ) return false;
		if ( ( sep2=list.find(',', pos) )==string::npos ) sep2 = list.length();

		name = list.substr(pos,sep1-pos);
		val_str = list.substr(sep1+1,sep2-sep1-1);
		debug("parsing preset '%s' = '%s'",name.c_str(),val_str.c_str());

		try {
			value = value_t(mpz_class(val_str));
		} catch ( std::invalid_argument ) {
			try {
				value = value_t(mpf_class(val_str));
			} catch ( ... ) { return false; }
		} catch ( ... ) { return false; }

		preset[name] = value;

		pos = sep2 + 1;
	}

	return true;
}
