(* $Id$
 *
 * Floods stdout and should fail with TIME-LIMIT or RUN-ERROR
 * depending on whether timelimit or filesize limit is reached first.
 *)

program fillstdout(input, output);

begin
   While 1=1 Do
   begin
	  writeln('Fill stdout with nonsense, to test filesystem stability.');
   end;
end.
