// This should give CORRECT on the default problem 'hello'.
//
// @EXPECTED_RESULTS@: CORRECT

if ( process.env.DOMJUDGE ) {
  console.log('Hello world!');
} else {
  console.log('DOMJUDGE not defined');
  process.exit(1);
}
