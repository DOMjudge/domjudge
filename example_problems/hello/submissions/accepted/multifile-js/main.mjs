// This should give CORRECT on the default problem 'hello'.
// It does include another file for the actual implementation
//
// @EXPECTED_RESULTS@: CORRECT

import { hello } from './module.mjs';
console.log(hello());
