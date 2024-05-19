// This should give RUN-ERROR on the default problem 'hello'.
// It tries to include another file for the actual implementation
// with the wrong extension.
//
// @EXPECTED_RESULTS@: RUN-ERROR

import { hello } from './module.mjs';
console.log(hello());
