--
-- This should give CORRECT on the default problem 'hello'.
--
-- @EXPECTED_RESULTS@: CORRECT
--
with Ada.Text_IO; use Ada.Text_IO;
procedure Hello is
begin
    Put_Line ("Hello world!");
end Hello;

