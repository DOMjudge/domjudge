#ifndef _SCANNER_H_
#define _SCANNER_H_

#if ! defined(_SKIP_YYFLEXLEXER_) && ! defined(_SYSINC_FLEXLEXER_H_)
#include <FlexLexer.h>
#define _SYSINC_FLEXLEXER_H_
#endif

#include <stdio.h>
#include "parserbase.h"

class Scanner: public yyFlexLexer
{
	Parser::LTYPE__ *d_loc;
	Parser::STYPE__ *d_val;

	public:
		Scanner(Parser::LTYPE__ *loc, Parser::STYPE__ *val,
		        std::istream* yyin  = 0,
		        std::ostream* yyout = 0)
		    : yyFlexLexer(yyin,yyout), d_loc(loc), d_val(val)
        {}

        int yylex();
};

#endif
