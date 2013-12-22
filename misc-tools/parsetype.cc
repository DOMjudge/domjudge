#include "parsetype.h"

std::ostream &operator<<(std::ostream &out, const parse_t &obj)
{
	char op = obj.op;

	// '#' should never be output as operator
	switch ( op ) {
	case 'i':
	case 'f':
	case ' ': out << obj.val;   op = ','; break;
	case 'n': out << '-';       op = '#'; break;
	case '!': out << '!';       op = '#'; break;
	case '(':                   op = '#'; break;
	case 'E': out << "ISEOF";   op = '#'; break;
	case 'M': out << "MATCH";   op = '#'; break;
	case 'U': out << "UNIQUE";  op = '#'; break;
	case 'A': out << "INARRAY"; op = '#'; break;
	}

	// Special case quote strings
	if ( op=='s' ) return out << '"' << obj.val << '"';

	// Special case compare operators, as these are not stored in 'op'
	if ( op=='?' ) {
		if ( obj.nargs()!=2 ) return out << "#error in compare#";
		out << obj.args[0] << obj.val << obj.args[1];
		return out;
	}

	// Special case array variable using []
	if ( op=='v' ) {
		out << obj.val;
		if ( obj.nargs()>0 ) {
			out << '[' << obj.args[0];
			for(size_t i=1; i<obj.nargs(); i++) out << ',' << obj.args[i];
			out << ']';
		}
		return out;
	}

	if ( obj.nargs()>0 ) {
		out << '(' << obj.args[0];
		for(size_t i=1; i<obj.nargs(); i++) out << op << obj.args[i];
		out << ')';
	}
    return out;
}
