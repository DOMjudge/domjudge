/*
 * This source is missing a class with correct main signature. In
 * DOMjudge we detect the main class during compilation, so this
 * should give a compilation error. On other systems, the main class
 * might be specified during submission; then this source would result
 * in a run-error.
 *
 * @EXPECTED_RESULTS@: COMPILER-ERROR
 */

import java.io.*;

public class main {
	int main() {
		System.out.println("Hello world!");
		return 0;
	}
};
