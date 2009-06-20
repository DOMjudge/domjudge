%baseclass-preinclude "parsetype.h"

%filenames parser
%scanner scanner.h

%lsp-needed
%lines
//%debug

%stype parse_t

%token CMD_SPACE CMD_NEWLINE CMD_EOF CMD_INT CMD_STRING CMD_REGEX CMD_REP CMD_END
%token VARIABLE INTEGER STRING

/*
  Command syntax below. All commands are uppercase, while variables
  are lowercase with non-leading digits.

  SPACE / NEWLINE

      No-argument commands matching a single space (0x20) or newline
      respectively.

  INT(value <min>, value <max> [,identifier <name>])

      Match an arbitrary sized integer value in the interval [min,max]
      and optionally assign the value read to variable 'name'.

  STRING(string <str>)

      Match the literal string 'str'.

  REGEX(string <str>)

      Match the extended regular expression 'str'. Matching is
      performed greedily.

  REP(value <count> [,command <separator>]) [COMMAND...] END

      Repeat the commands between the 'REP() ... END' statements count
      times and optionally match separator command (count-1) times
      in between.
*/

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
