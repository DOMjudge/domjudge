/*
 * Library to verify testdata or program output syntax.
 */

#ifndef LIBCHECKTESTDATA_H
#define LIBCHECKTESTDATA_H

#include <iostream>

const int exit_failure = 2;

const int opt_whitespace_ok = 1; // ignore additional whitespace
const int opt_quiet         = 2; // quiet execution: only return status
const int opt_debugging     = 4; // print additional debugging statements

bool checksyntax(std::istream &progstream,
                 std::istream &datastream, int opt_mask = 0);
/* Check testdata input/output in datastream against syntax specified
 * in progstream. Additional options can be specified in opt_mask.
 * Returns 'true' if the syntax is completely valid.
 */

void gentestdata(std::istream &progstream,
                 std::ostream &datastream, int opt_mask = 0);

#endif /* LIBCHECKTESTDATA_H */
