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
		int i;
		int[][] ar = new int[10240][];

		for(i=0; 1==1; i++) {
			ar[i] = new int[1024*1024/4];
			System.out.print("allocated " + (i+1) + " MB of memory\n");
		}
    }
}
