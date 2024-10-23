// This should give CORRECT on the default problem 'hello'.
// The submission includes another file with invalid syntax
// which is never used. As we check all files for syntax we
// also check the unused but invalid other file resulting in
// the (unneeded) error.
//
// @EXPECTED_RESULTS@: COMPILER-ERROR

console.log("Hello world!");
