/*
 * This should give CORRECT on the default problem 'hello'. Note that
 * it will fail with RUN-ERROR if using the (default) chroot environment.
 *
 * @EXPECTED_RESULTS@: CORRECT
 */

using System;

public class Hello
{
	public static void Main(string[] args)
	{
		Console.Write("Hello world!\n");
	}
}
