/*
 * The result of this source depends on the specific java compile
 * script which is used. The java_javac script requires a class 'Main'
 * with 'public static void main(String[] args)' method and will thus
 * fail with compiler-error. The java_javac_detect script autodetects the
 * class containing the main method and should correctly compile. Note
 * that to successfully compile, the source also has to be renamed to
 * match the _public_ class name 'foo'. It is preferred to not define
 * classes public.
 *
 * @EXPECTED_RESULTS@: CORRECT,COMPILER-ERROR
 */

import java.io.*;

public class foo {
	public static void main(String[] args) {
		System.out.println("Hello world!");
	}
};
