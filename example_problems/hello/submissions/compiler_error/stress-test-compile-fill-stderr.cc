/*
 * This program should fail with a COMPILER-ERROR due to a timeout.
 * The compiler errors flooding stderr should be truncated to the
 * compile_filesize setting and not affect system stability.
 *
 * Code from http://tgceec.tumblr.com/
 *
 * @EXPECTED_RESULTS@: COMPILER-ERROR
 */

template<class T>class L{L<T*>operator->()};L<int>i=i->
