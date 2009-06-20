#ifndef PARSETYPE_H
#define PARSETYPE_H

#include <string>
#include <vector>
#include <iostream>

struct parse_t;

typedef std::string val_t;
typedef std::vector<parse_t> args_t;

typedef parse_t command;

extern std::vector<command> program;

struct parse_t {
	val_t val;
	args_t args;

	parse_t(): val(), args() {};
	parse_t(val_t str): val(str), args() {};
	parse_t(args_t _args): val(), args(_args) {};
	parse_t(val_t _val, args_t _args): val(_val), args(_args) {};
	parse_t(val_t _val, parse_t arg1): val(_val), args()
	{
		args.push_back(arg1);
	};
	parse_t(val_t _val, parse_t arg1, parse_t arg2): val(_val), args()
	{
		args.push_back(arg1);
		args.push_back(arg2);
	};
	parse_t(val_t _val, parse_t arg1, parse_t arg2, parse_t arg3): val(_val), args()
	{
		args.push_back(arg1);
		args.push_back(arg2);
		args.push_back(arg3);
	};

	const val_t& name()  const { return val; }
	size_t       nargs() const { return args.size(); }

	operator std::string() { return val; }
};

inline std::ostream &operator<<(std::ostream &out, const parse_t &obj)
{
	out << obj.val;
	if ( obj.nargs()>0 ) {
		out << '(' << obj.args[0];
		for(size_t i=1; i<obj.nargs(); i++) out << ',' << obj.args[i];
		out << ')';
	}
    return out;
}

#endif
