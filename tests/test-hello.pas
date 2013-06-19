(*
 * This should give CORRECT on the default problem 'hello'.
 *
 * @EXPECTED_RESULTS@: CORRECT
 *)

program helloworld(input, output);

var
   hello : string;

begin
   hello := 'Hello world!';
{$ifdef DOMJUDGE}
   writeln(hello);
{$else}
   writeln('DOMJUDGE not defined');
{$endif}
end.
