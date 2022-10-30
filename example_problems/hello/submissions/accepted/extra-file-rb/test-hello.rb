# This should give CORRECT on the default problem 'hello',
# since the random extra file will not be passed.
#
# @EXPECTED_RESULTS@: CORRECT

if ENV['DOMJUDGE'] != '' then
  puts "Hello world!"
else
  puts "DOMJUDGE not defined"
  exit(1)
end
