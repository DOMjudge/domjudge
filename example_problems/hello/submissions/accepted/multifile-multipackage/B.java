/*
 * Tests that multifile java submissions work even with multiple packages.
 *
 * @EXPECTED_RESULTS@: CORRECT
 */

package zoo.goo.moo.b;

import foo.bar.a.A;

public class B {
	public static void main(String[] args) {
		A.foo();
	}
}
