using System;

public class Hello
{
	public static void Main(string[] args)
	{
		int nTests = int.Parse(Console.ReadLine());

		for (int i = 0; i < nTests; i++) {
			string name = Console.ReadLine();
			Console.WriteLine("Hello "+name+"!");
		}
	}
}
