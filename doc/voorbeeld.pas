program voorbeeld(input, output);

var
   aantaltests, test : integer;
   naam				 : string[100];

begin
   readln(aantaltests);

   for test := 1 to aantaltests do
   begin
	  readln(naam);
	  writeln('Hallo ',naam,'!');
   end;
end.
