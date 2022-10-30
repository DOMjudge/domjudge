/*
 * This multifile submission should give COMPILER-ERROR on the default
 * problem 'hello'. It contains object code (from
 * test-multifile.cpp.zip) that, if linked, will result in CORRECT,
 * but this should not be allowed, since then teams could send in any
 * precompiled code in any language.
 *
 * @EXPECTED_RESULTS@: COMPILER-ERROR
 */

#include "message.hpp"

int main()
{
	message msg;

	msg.print();

	return 0;
}
