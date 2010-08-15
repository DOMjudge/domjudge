/* $Id$
 *
 * This should give CORRECT on the default problem 'hello'.
 * If the compiled code is copied into the root of the judging chroot
 * directory however, this will crash the judgedaemon when it tries to
 * create the /bin directory for the static shell.
 *
 * @EXPECTED_RESULTS@: CORRECT
 */

package bin;

import java.io.*;

class Main {
    public static void main(String[] args) throws Exception {
		System.out.print("Hello world!\n");
    }
}
