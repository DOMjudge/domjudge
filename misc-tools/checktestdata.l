%{
    #define _SKIP_YYFLEXLEXER_
    #include "scanner.h"
    #include "parser.h"

	#include <string>
%}

%option yyclass="Scanner" outfile="yylex.cc"
%option c++ 8bit warn noyywrap yylineno

	/* start condition for strings */
	std::string str_buf;

%x string

%%

ISEOF    { return Parser::TEST_EOF; }
MATCH    { return Parser::TEST_MATCH; }

\<       { return Parser::CMP_LT; }
>        { return Parser::CMP_GT; }
\<=      { return Parser::CMP_LE; }
>=       { return Parser::CMP_GE; }
==       { return Parser::CMP_EQ; }
!=       { return Parser::CMP_NE; }

SPACE    { return Parser::CMD_SPACE; }
NEWLINE  { return Parser::CMD_NEWLINE; }
EOF      { return Parser::CMD_EOF; }
INT      { return Parser::CMD_INT; }
FLOAT    { return Parser::CMD_FLOAT; }
STRING   { return Parser::CMD_STRING; }
REGEX    { return Parser::CMD_REGEX; }
REP      { return Parser::CMD_REP; }
WHILE    { return Parser::CMD_WHILE; }
END      { return Parser::CMD_END; }

FIXED      { return Parser::OPT_FIXED; }
SCIENTIFIC { return Parser::OPT_SCIENTIFIC; }

[a-z][a-z0-9]* {
	return Parser::VARIABLE;
}

 /* A floating point number must have a decimal point or exponent: */
[0-9]+\.[0-9]+([eE][+-]?[0-9]+)? {
	return Parser::FLOAT;
}

[0-9]+[eE][+-]?[0-9]+ {
	return Parser::FLOAT;
 }

[0-9]+ {
	return Parser::INTEGER;
}

 /* String parsing -- adapted from the flex manual page */

\" {
	/* start of string */
	str_buf.clear();
	BEGIN(string);
}

<string>{
\" {
	/* end of string */
	BEGIN(INITIAL);
	yytext = strdup(str_buf.c_str());
	return Parser::STRING;
}

\n {
	/* allow string constants to span multiple lines */
	str_buf += '\n';
}

\\[0-7]{1,3} {
	/* octal escape sequence */
	int result;

	(void) sscanf(yytext+1,"%o",&result);

	/* should check for bounds of escape sequence: result < 256 */
	str_buf += (char) result;
}

\\[0-9]+ {
	/* error: bad escape sequence */
}

	/* Escape sequences for some special characters */
\\n         str_buf += '\n';
\\t         str_buf += '\t';
\\r         str_buf += '\r';
\\b         str_buf += '\b';
\\f         str_buf += '\f';
\\\"        str_buf += '"';
\\\\        str_buf += '\\';

\\\n {
	/* ignore escaped newlines */
}

	/* treat all other backslashes normally */
\\.         str_buf += yytext;

	/* Fast match any text not containing special characters */
[^\\\n\"]+ str_buf += yytext;

} /* end of string parsing */

[ \t\r\n]+ {
	/* ignore whitespace and newlines */
}

^#.*\n {
	/* ignore comment lines starting with '#' */
}

. {
	/* return all others characters as-is. */
	return yytext[0];
}

%%
