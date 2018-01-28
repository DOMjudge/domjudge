/*
 * Sample solution in Java for the "boolfind" interactive problem.
 *
 * @EXPECTED_RESULTS@: CORRECT
 */

import java.io.*;

class Main {
	public static BufferedReader in;

	public static void main(String[] args) throws Exception {
		in = new BufferedReader(new InputStreamReader(System.in));

		int nruns = Integer.parseInt(in.readLine());

		for (int run = 1; run <= nruns; run++) {
			long n, lo, hi, mid;
			String answer;

			n = Integer.parseInt(in.readLine());

			lo = 0;
			hi = n;
			while (lo+1 < hi) {
				mid = (lo+hi)/2;
				System.out.println("READ " + mid);
				answer = in.readLine();
				if (answer.equals("true")) {
					lo = mid;
				} else if (answer.equals("false")) {
					hi = mid;
				} else {
					throw new Exception("invalid return value '" + answer + "'");
				}
			}

			System.out.println("OUTPUT " + lo);
		}
	}
}
