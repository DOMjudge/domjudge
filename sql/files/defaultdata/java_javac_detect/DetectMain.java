/*
 * Detect Java main function for the given list of classes.
 * First argument is the search directory.
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

import java.lang.reflect.*;
import java.net.*;

public class DetectMain {
	public static void main ( String[] args ) {
		// Check arguments
		if ( args.length < 2 ) {
			System.err.println("Usage: java DetectMain <dir> <class1> "
			                   + "[ <class2> ... ]");
			System.exit(1);
			return;
		}

		// Load classes from specific directory
		URL dir;
		try {
			dir = new URL("file://" + args[0] + "/");
		} catch ( MalformedURLException e ) {
			System.err.println("Error: malformed directory '" + args[0] + "'");
			System.exit(1);
			return;
		}
		ClassLoader cl = new URLClassLoader( new URL[] { dir } );

		// Go through all classes
		String result = null;
		for ( int i = 1; i < args.length; i++) {
			String arg = args[i];

			// Instantiate class
			Class<?> c;
			try {
				c = Class.forName(arg, false, cl);
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
