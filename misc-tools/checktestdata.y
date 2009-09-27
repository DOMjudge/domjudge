%baseclass-preinclude "parsetype.h"

%filenames parser
%scanner scanner.h

%lsp-needed
%lines
//%debug

%stype parse_t

%token CMD_SPACE CMD_NEWLINE CMD_EOF CMD_INT CMD_STRING CMD_REGEX CMD_REP CMD_END
%token VARIABLE INTEGER STRING

%left '+' '-'
%left '*' '/' '%'

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
	command_noargs
|
	command_args
;

command_noargs:
	CMD_SPACE   { $$ = parse_t($1); }
|	CMD_NEWLINE { $$ = parse_t($1); }
|	CMD_EOF     { $$ = parse_t($1); }
|	CMD_END     { $$ = parse_t($1); }
;

command_args:
	CMD_INT '(' expr ',' expr ')'              { $$ = parse_t($1,$3,$5); }
|	CMD_INT '(' expr ',' expr ',' VARIABLE ')' { $$ = parse_t($1,$3,$5,$7); }
|	CMD_STRING '(' STRING ')'                  { $$ = parse_t($1,$3); }
|	CMD_REGEX  '(' STRING ')'                  { $$ = parse_t($1,$3); }
| 	CMD_REP '(' expr ')'                       { $$ = parse_t($1,$3); }
| 	CMD_REP '(' expr ',' command ')'           { $$ = parse_t($1,$3,$5); }
;

value:
	INTEGER
|
	VARIABLE
;

expr:
	term          { $$ = parse_t('(',$1); }
|	expr '+' term { $$ = parse_t('+',$1,$3); }
|	expr '-' term { $$ = parse_t('-',$1,$3); }
;

term:
	value         { $$ = parse_t('(',$1); }
|	'-' term      { $$ = parse_t('n',$2); }
|	'(' expr ')'  { $$ = parse_t('(',$2); }
|	term '*' term { $$ = parse_t('*',$1,$3); }
|	term '/' term { $$ = parse_t('/',$1,$3); }
|	term '%' term { $$ = parse_t('%',$1,$3); }
;
