!
! This should give CORRECT on the default problem 'hello',
! since the random extra file will not be passed.
!
! @EXPECTED_RESULTS@: CORRECT
!
program hello
write(*,"(A)") "Hello world!"
end program hello

