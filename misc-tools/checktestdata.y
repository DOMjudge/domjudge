%baseclass-preinclude "parsetype.h"

%filenames parser

%scanner scanner.h

%lsp-needed
//%debug

%stype parse_t

%token TEST_EOF TEST_MATCH TEST_UNIQUE TEST_INARRAY
%token CMP_LT CMP_GT CMP_LE CMP_GE CMP_EQ CMP_NE
%token CMD_SPACE CMD_NEWLINE CMD_EOF CMD_INT CMD_FLOAT CMD_STRING CMD_REGEX
%token CMD_ASSERT CMD_UNSET
%token CMD_REP CMD_WHILE CMD_REPI CMD_WHILEI CMD_IF CMD_ELSE CMD_END
%token VARNAME INTEGER FLOAT STRING
%token OPT_FIXED OPT_SCIENTIFIC

%left LOGIC_AND LOGIC_OR
%left '+' '-'
%left '*' '/' '%'
%left '^'

%%

commands:
	// empty
|
	commands command
{
	program.push_back($2);
}
;

command:
	CMD_SPACE
|	CMD_NEWLINE
|	CMD_EOF
|	CMD_END
|	CMD_ELSE
|	CMD_INT    '(' expr ',' expr ')'                 { $$ = parse_t($1,$3,$5); }
|	CMD_INT    '(' expr ',' expr ',' variable ')'    { $$ = parse_t($1,$3,$5,$7); }
|	CMD_FLOAT  '(' expr ',' expr ')'                 { $$ = parse_t($1,$3,$5); }
|	CMD_FLOAT  '(' expr ',' expr ',' variable ')'    { $$ = parse_t($1,$3,$5,$7); }
|	CMD_FLOAT  '(' expr ',' expr ',' variable ',' opt_float ')'
	                                                 { $$ = parse_t($1,$3,$5,$7,$9); }
|	CMD_STRING '(' string ')'                        { $$ = parse_t($1,$3); }
|	CMD_REGEX  '(' string ')'                        { $$ = parse_t($1,$3); }
|	CMD_REGEX  '(' string ',' variable ')'           { $$ = parse_t($1,$3,$5); }
|	CMD_ASSERT '(' test ')'                          { $$ = parse_t($1,$3); }
|	CMD_UNSET  '(' varlist ')'                       { $$ = parse_t('@',$1,$3); }
|	CMD_REP    '(' expr ')'                          { $$ = parse_t($1,$3); }
|	CMD_REP    '(' expr ',' command ')'              { $$ = parse_t($1,$3,$5); }
|	CMD_WHILE  '(' test ')'                          { $$ = parse_t($1,$3); }
|	CMD_WHILE  '(' test ',' command ')'              { $$ = parse_t($1,$3,$5); }
|	CMD_IF     '(' test ')'                          { $$ = parse_t($1,$3); }
|	CMD_REPI   '(' variable ',' expr ')'             { $$ = parse_t($1,$3,$5); }
|	CMD_REPI   '(' variable ',' expr ',' command ')' { $$ = parse_t($1,$3,$5,$7); }
|	CMD_WHILEI '(' variable ',' test ')'             { $$ = parse_t($1,$3,$5); }
|	CMD_WHILEI '(' variable ',' test ',' command ')' { $$ = parse_t($1,$3,$5,$7); }
;

opt_float: OPT_FIXED | OPT_SCIENTIFIC ;

string:
	STRING    { $$ = parse_t('s',$1); }
;

value:
	INTEGER   { $$ = parse_t('i',$1); }
|	FLOAT     { $$ = parse_t('f',$1); }
|	string
|	variable
;

variable:
	VARNAME                  { $$ = parse_t('v',$1); }
|	VARNAME '[' exprlist ']' { $$ = parse_t('v',$1,$3); }
;

exprlist:
	expr                     { $$ = parse_t('l',$1); }
|	exprlist ',' expr        { $$ = parse_t('l',$1,$3); }
;

varlist:
	VARNAME                  { $$ = parse_t('l',$1); }
|	varlist ',' VARNAME      { $$ = parse_t('l',$1,$3); }
;

compare: CMP_LT | CMP_GT | CMP_LE | CMP_GE | CMP_EQ | CMP_NE ;

expr:
	term          { $$ = parse_t($1); }
|	expr '+' term { $$ = parse_t('+',$1,$3); }
|	expr '-' term { $$ = parse_t('-',$1,$3); }
;

term:
	fact          { $$ = parse_t($1); }
|	term '*' fact { $$ = parse_t('*',$1,$3); }
|	term '/' fact { $$ = parse_t('/',$1,$3); }
|	term '%' fact { $$ = parse_t('%',$1,$3); }
;

fact:
	value         { $$ = parse_t($1); }
|	'-' fact      { $$ = parse_t('n',$2); }
|	'(' expr ')'  { $$ = parse_t($2); }
|	fact '^' fact { $$ = parse_t('^',$1,$3); }
;

test:
	'!' test      { $$ = parse_t('!',$2); }
|	'(' test ')'  { $$ = parse_t($2); }
|	test LOGIC_AND test                     { $$ = parse_t('&',$1,$3); }
|	test LOGIC_OR  test                     { $$ = parse_t('|',$1,$3); }
|	expr compare expr                       { $$ = parse_t('?',$2,$1,$3); }
|	TEST_EOF                                { $$ = parse_t('E'); }
|	TEST_MATCH '(' string ')'               { $$ = parse_t('M',$3); }
|	TEST_UNIQUE '(' varlist ')'             { $$ = parse_t('U',$3); }
|	TEST_INARRAY '(' expr ',' variable ')'  { $$ = parse_t('A',$3,$5); }
;
