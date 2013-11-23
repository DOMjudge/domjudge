# This should give CORRECT on the default problem 'hello'.
#
# @EXPECTED_RESULTS@: CORRECT

if ENV['DOMJUDGE'] != '' then
  puts "Hello world!"
else
  puts "DOMJUDGE not defined"
  exit(1)
end
