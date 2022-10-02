(*
 * This should give CORRECT on the default problem 'hello'.
 * With older compile scripts, it might try to compile the hello.pp
 * unit file, leading to a COMPILER-ERROR since the target binary will
 * be missing.
 *
 * @EXPECTED_RESULTS@: CORRECT
 *)

program helloworldunit(input, output);
uses hello;

begin
   printhello();
end.
