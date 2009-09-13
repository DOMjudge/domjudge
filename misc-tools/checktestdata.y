%baseclass-preinclude "parsetype.h"

%filenames parser
%scanner scanner.h

%lsp-needed
%lines
//%debug

%stype parse_t

%token CMD_SPACE CMD_NEWLINE CMD_EOF CMD_INT CMD_STRING CMD_REGEX CMD_REP CMD_END
%token VARIABLE INTEGER STRING

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
	CMD_INT '(' value ',' value ')'              { $$ = parse_t($1,$3,$5); }
|	CMD_INT '(' value ',' value ',' VARIABLE ')' { $$ = parse_t($1,$3,$5,$7); }
|	CMD_STRING '(' STRING ')'                    { $$ = parse_t($1,$3); }
|	CMD_REGEX  '(' STRING ')'                    { $$ = parse_t($1,$3); }
| 	CMD_REP '(' value ')'                        { $$ = parse_t($1,$3); }
| 	CMD_REP '(' value ',' command ')'            { $$ = parse_t($1,$3,$5); }
;

value:
	INTEGER
|
	VARIABLE
;
