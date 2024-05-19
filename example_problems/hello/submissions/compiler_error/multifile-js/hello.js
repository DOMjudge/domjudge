// This should give COMPILER-ERROR on the default problem 'hello'.
// The included file is invalid syntax.
//
// @EXPECTED_RESULTS@: COMPILER-ERROR

import { hello } from './module.js';
console.log(hello());
