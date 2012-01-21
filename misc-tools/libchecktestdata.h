#include <iostream>

const int exit_failure = 2;

const int opt_whitespace_ok = 1;
const int opt_quiet         = 2;
const int opt_debugging     = 4;

bool checksyntax(std::istream &progstream, std::istream &datastream, int opt_mask);
