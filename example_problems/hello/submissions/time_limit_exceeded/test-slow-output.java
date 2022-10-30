/*
 * Output answers slowly, should give TIMELIMIT with some output
 *
 * @EXPECTED_RESULTS@: TIMELIMIT
 */

import java.io.*;

class Main {
    public static void main(String[] args) throws Exception {
        int i,j,k,l,x;

        for(i=10; true; i+=10) {
			x = 0;
			for(j=0; j<i; j++)
				for(k=0; k<i; k++)
					for(l=0; l<i; l++)
						x++;

			System.out.println(i + " ^ 3 = " + x);
        }
    }
}
