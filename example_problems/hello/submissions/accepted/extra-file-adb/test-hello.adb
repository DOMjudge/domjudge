--
-- This should give CORRECT on the default problem 'hello',
-- since the random extra file will not be passed.
--
-- @EXPECTED_RESULTS@: CORRECT
--
with Ada.Text_IO; use Ada.Text_IO;
procedure Hello is
begin
    Put_Line ("Hello world!");
end Hello;

