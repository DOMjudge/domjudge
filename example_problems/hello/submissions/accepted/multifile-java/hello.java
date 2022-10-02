/*
 * This is a multi-file submission which produces two public classes.
 * Note that to successfully compile, the source file names must be
 * preserved to match the public class names.
 *
 * @EXPECTED_RESULTS@: CORRECT
 */

import java.io.*;

public class hello {
	public static void main(String[] args) {
		message foo = new message();
		foo.print();
	}
};
