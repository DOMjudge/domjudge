<?php declare(strict_types=1);

// Mapping of DOMjudge verdict strings to those defined in the
// CCS specification (and a few more common ones) at:
// https://ccs-specs.icpc.io/ccs_system_requirements#judge-responses
return [
    'compiler-error'     => 'CE',
    'memory-limit'       => 'MLE',
    'output-limit'       => 'OLE',
    'run-error'          => 'RTE',
    'timelimit'          => 'TLE',
    'wrong-answer'       => 'WA',
    'presentation-error' => 'PE', /* dropped since 5.0 */
    'no-output'          => 'NO',
    'correct'            => 'AC',
    ''                   => 'JE', /* happens for aborted judgings */
];
