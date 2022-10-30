/*
 * With the default Java compile script that autodetects the class
 * name, this should give CORRECT on the default problem 'hello'.
 * If the compiled code is copied into the root of the judging chroot
 * directory however, this will crash the judgedaemon when it tries to
 * create the /bin directory for the static shell.
 *
 * Using the non-autodetecting Java compile script, this fails with
 * compiler error.
 *
 * @EXPECTED_RESULTS@: CORRECT,COMPILER-ERROR
 */

package bin;

import java.io.*;

class Main {
    public static void main(String[] args) throws Exception {
		System.out.print("Hello world!\n");
    }
}
