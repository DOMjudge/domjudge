/*
 * Detect Java main function for the given list of classes.
 * Warning: Does not work when one of the class names is 'DetectMain'.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

import java.lang.reflect.*;

public class DetectMain {
	public static void main ( String[] args ) {
		String result = null;

		// Go through all classes
		for ( String arg : args ) {
			// Instantiate class
			Class<?> c;
			try {
				c = Class.forName(arg);
			} catch ( ClassNotFoundException e ) {
				System.err.println("Error: class '" + arg + "' not found.");
				System.exit(1);
				return;
			}

			// Try to find main method
			Method mainMethod;
			try {
				mainMethod = c.getDeclaredMethod("main", args.getClass());
			} catch ( NoSuchMethodException e ) {
				// Silently ignore, not every class has main method
				continue;
			}

			// Check if it's indeed public static void
			if ( Modifier.isStatic(mainMethod.getModifiers())
			   && Modifier.isPublic(mainMethod.getModifiers())
			   && mainMethod.getReturnType().equals(Void.TYPE) ) {
				if ( result != null ) {
					System.err.println("Warning: found another 'main' in '" 
					                   + arg + "'");
				}
				else {
					System.err.println("Info: using 'main' from '" + arg + "'");
					result = arg;
				}
			}
		}

		// No main found
		if ( result == null ) {
			System.err.println("Error: no 'main' found in any class file.");
			System.exit(1);
			return;
		}

		// Success
		System.out.println(result);
	}
}
