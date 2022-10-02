(*
 * This should give CORRECT on the default problem 'hello',
 * It used to give COMPILER-ERROR since it includes a random extra file.
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
