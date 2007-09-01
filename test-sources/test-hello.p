(* $Id$
 *
 * This should give CORRECT on the default problem 'hello'.
 *)

program helloworld(input, output);

var
   hello : string;
   
begin
   hello := 'Hello world!';
   writeln(hello);
end.
