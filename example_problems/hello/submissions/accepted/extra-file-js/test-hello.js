// This should give CORRECT on the default problem 'hello'.
// It used to give COMPILER-ERROR since it includes a random extra file.
//
// @EXPECTED_RESULTS@: CORRECT

if ( process.env.DOMJUDGE ) {
  console.log('Hello world!');
} else {
  console.log('DOMJUDGE not defined');
  process.exit(1);
}
