program example(input, output);

var
	ntests, test : integer;
	name         : string[100];

begin
	readln(ntests);

	for test := 1 to ntests do
	begin
		readln(name);
		writeln('Hello ', name, '!');
	end;
end.
