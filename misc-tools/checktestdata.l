%filenames scanner

// Define an exclusive sub-scanner:
%x string

%%

ISEOF    { return Parser::TEST_EOF; }
MATCH    { return Parser::TEST_MATCH; }
UNIQUE   { return Parser::TEST_UNIQUE; }
INARRAY  { return Parser::TEST_INARRAY; }

&&       { return Parser::LOGIC_AND; }
\|\|     { return Parser::LOGIC_OR; }

\<       { return Parser::CMP_LT; }
\>       { return Parser::CMP_GT; }
\<=      { return Parser::CMP_LE; }
\>=      { return Parser::CMP_GE; }
==       { return Parser::CMP_EQ; }
!=       { return Parser::CMP_NE; }

SPACE    { return Parser::CMD_SPACE; }
NEWLINE  { return Parser::CMD_NEWLINE; }
EOF      { return Parser::CMD_EOF; }
INT      { return Parser::CMD_INT; }
FLOAT    { return Parser::CMD_FLOAT; }
STRING   { return Parser::CMD_STRING; }
REGEX    { return Parser::CMD_REGEX; }
ASSERT   { return Parser::CMD_ASSERT; }
UNSET    { return Parser::CMD_UNSET; }
REP      { return Parser::CMD_REP; }
WHILE    { return Parser::CMD_WHILE; }
REPI     { return Parser::CMD_REPI; }
WHILEI   { return Parser::CMD_WHILEI; }
IF       { return Parser::CMD_IF; }
ELSE     { return Parser::CMD_ELSE; }
END      { return Parser::CMD_END; }

FIXED      { return Parser::OPT_FIXED; }
SCIENTIFIC { return Parser::OPT_SCIENTIFIC; }

[a-z][a-z0-9]* {
	return Parser::VARNAME;
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

 /* String parsing -- adapted from the flex and flexc++ manuals */

\" {
	/* begin of string */
	begin(StartCondition__::string);
}

<string>{
\" {
	/* end of string */
	setMatched(matched().substr(0,matched().length()-1));
	begin(StartCondition__::INITIAL);
	return Parser::STRING;
}

\n {
	/* Allow string constants to span multiple lines. */
	more();
}

\\[0-7]{1,3} {
	/* octal escape sequence */
	size_t esc_pos = matched().rfind('\\');
	std::string oct_str = matched().substr(esc_pos+1);
	int res;
	size_t pos = 0;

	try {
		res = std::stoi(oct_str,&pos,8);
	} catch ( ... ) {
		res = -1;
	}

	if ( pos!=oct_str.length() || res<0 || res>=256 ) {
		throw(ScannerException("bad escape sequence '\\"+oct_str+
		                       "' on line "+std::to_string(lineNr())));
	}
	setMatched(matched().substr(0,esc_pos)+(char)res);
	more();
}

\\[ntrbf\"\\] {
	/* Escape sequences for some special characters */
	char c = matched().back(), res;
	switch ( c ) {
	case 'n':  res = '\n'; break;
	case 't':  res = '\t'; break;
	case 'r':  res = '\r'; break;
	case 'b':  res = '\b'; break;
	case 'f':  res = '\f'; break;
	case '\"': res = '\"'; break;
	case '\\': res = '\\'; break;
	default:
		throw(ScannerException("unknown escape character '\\"+std::string(1,c)+
		                       "' on line "+std::to_string(lineNr())));
	}
	setMatched(matched().substr(0,matched().length()-2)+res);
	more();
}

\\\n {
	/* ignore escaped newlines */
	setMatched(matched().substr(0,matched().length()-2));
	more();
}

	/* treat all other backslashes normally */
\\.     more();

[^\\\n\"]+ {
	/* Fast match any text not containing special characters. */
	more();
}

} /* end of string parsing */

[ \t\r\n]+ {
	/* ignore whitespace and newlines */
}

^#.*\n {
	/* ignore comment lines starting with '#' */
}

. {
	/* return all others characters as-is. */
	return matched()[0];
}

%%
