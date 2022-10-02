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
   writeln(hello);
end.
