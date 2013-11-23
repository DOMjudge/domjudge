#ifndef PARSETYPE_H
#define PARSETYPE_H

#include <string>
#include <vector>
#include <iostream>

struct parse_t;

typedef std::string val_t;
typedef std::vector<parse_t> args_t;

typedef parse_t command;
typedef parse_t expr;
typedef parse_t test;

extern std::vector<command> program;

std::ostream &operator<<(std::ostream &, const parse_t &);

struct parse_t {
	val_t val;
	args_t args;
	char op;
	/*
	  Operator/type of this node, can be any of the following:

	  +-*%/^ standard binary arithmetic operations
	  n      (unary) negation

	  ?      a comparison operator stored in 'val'
	  |&!    logical AND,OR,NOT
	  EMUA   EOF,MATCH,UNIQUE,INARRAY keywords used within test expressions

	  l      list of expressions (e.g. for array indices or argument list)
	  v      variable with array indices in second argument
	  s      string constant
	  @      command with list of arguments provided in second argument,
	  ' '    command
	  ~      uninitialized object, to detect unset default arguments
	*/

	parse_t(): val(), args(), op('~') {}
	parse_t(args_t _args): val(), args(_args), op(' ') {}
	parse_t(val_t _val, args_t _args): val(_val), args(_args), op(' ') {}

	// Parsing command with optional arguments
	explicit
	parse_t(val_t _val, parse_t arg1 = parse_t(),
	                    parse_t arg2 = parse_t(),
	                    parse_t arg3 = parse_t(),
	                    parse_t arg4 = parse_t())
	: val(_val), args(), op(' ')
	{
		if ( arg1.op!='~' ) args.push_back(arg1);
		if ( arg2.op!='~' ) args.push_back(arg2);
		if ( arg3.op!='~' ) args.push_back(arg3);
		if ( arg4.op!='~' ) args.push_back(arg4);
	}

	// Parsing arithmetic/logical/compare operator and some other
	// special cases
	explicit
	parse_t(char _op, parse_t arg1 = parse_t(),
	                  parse_t arg2 = parse_t(),
	                  parse_t arg3 = parse_t(),
	                  parse_t arg4 = parse_t())
	: val(), args(), op(_op)
	{
		switch ( op ) {
		case 'l': // list: create new or append one argument
			if ( arg2.op=='~' ) {
				args.push_back(arg1);
			} else {
				args = arg1.args;
				args.push_back(arg2);
			}
			break;

		case 'i': // integer, float, string literal values
		case 'f':
		case 's':
			val = arg1.val;
			break;

		case 'v': // variable, read index from arg2 if present
			val = arg1.val;
			if ( arg2.op=='l' ) args = arg2.args;
			break;

		case '@': // Command with argument list in arg2
			op = ' ';
			val = arg1.val;
			args = arg2.args;
			break;

		case 'U': // UNIQUE test, has argument list in arg1
			args = arg1.args;
			break;

		case '?': // comparison operator as arg1
			val = arg1;
			args.push_back(arg2);
			args.push_back(arg3);
			break;

		default:
			if ( arg1.op!='~' ) args.push_back(arg1);
			if ( arg2.op!='~' ) args.push_back(arg2);
			if ( arg3.op!='~' ) args.push_back(arg3);
			if ( arg4.op!='~' ) args.push_back(arg4);
		}
	}

	const val_t& name()  const { return val; }
	size_t       nargs() const { return args.size(); }

	operator std::string() { return val; }
	const char *c_str() { return val.c_str(); }
};

#endif
