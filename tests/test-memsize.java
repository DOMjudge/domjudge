/*
 * This should fail with RUN-ERROR due to running out of memory, which
 * is restricted. Check the amount available, because the java binary
 * might consume a lot of the total memory available.
 *
 * @EXPECTED_RESULTS@: RUN-ERROR
 */

import java.io.*;

class Main {
    public static void main(String[] args) throws Exception {
		int[][] ar = new int[10240][];

		for(int i=0; true; i++) {
			ar[i] = new int[256*1024*1024];
			System.out.print("allocated " + 256*(i+1) + " MB of memory\n");
		}
    }
}
