/*
 * This is an auxiliary source file that declares another public class
 * 'message'.
 */

import java.io.*;

public class message {
	private String msg;

	public message() {
		msg = new String("Hello world!");
	}

	public void print() {
		System.out.println(msg);
	}
};
