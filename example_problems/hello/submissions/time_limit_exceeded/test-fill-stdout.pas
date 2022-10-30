(*
 * Floods stdout and should fail with TIMELIMIT, since output is
 * automatically truncated at the filesize limit.
 *
 * @EXPECTED_RESULTS@: TIMELIMIT
 *)

program fillstdout(input, output);

begin
   While 1=1 Do
   begin
	  writeln('Fill stdout with nonsense, to test filesystem stability.');
   end;
end.
