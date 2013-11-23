!
! This should give CORRECT on the default problem 'hello'.
!
! @EXPECTED_RESULTS@: CORRECT
!
program hello
#ifdef DOMJUDGE
    write(*,"(A)") "Hello world!"
#else
    write(*,"(A)") "DOMJUDGE not defined"
#endif
end program hello

