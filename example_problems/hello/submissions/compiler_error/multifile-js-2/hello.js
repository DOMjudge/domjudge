// This should give COMPILER-ERROR on the default problem 'hello'.
// The console.log is missing a `)`.
//
// @EXPECTED_RESULTS@: COMPILER-ERROR

import { hello } from './module.js';
console.log(hello();
