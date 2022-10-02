/*
 * This should give CORRECT on the default problem 'hello',
 * since the random extra file will not be passed.
 *
 * @EXPECTED_RESULTS@: CORRECT
 */

using System;

public class Hello
{
	public static void Main(string[] args)
	{
#if ONLINE_JUDGE
		Console.Write("Hello world!\n");
#else
		Console.Write("ONLINE_JUDGE not defined\n");
#endif
	}
}
