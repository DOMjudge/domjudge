/*
 * This source contains two files, each with a class with proper entry
 * point signature. The result depends on the configuration of the
 * Java compile script.
 *
 * @EXPECTED_RESULTS@: CORRECT,COMPILER-ERROR,WRONG-ANSWER
 */

import java.io.*;

public class a {
	public static void main(String[] args) {
		System.out.println("Hello world!");
	}
};
