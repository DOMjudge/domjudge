<?php declare(strict_types=1);

// Mapping of DOMjudge verdict strings to those defined in the
// CCS specification (and a few more common ones) at:
// https://ccs-specs.icpc.io/2021-11/ccs_system_requirements#judge-responses
return [
    'final' => [
        'compiler-error'     => 'CE',
        'memory-limit'       => 'MLE',
        'output-limit'       => 'OLE',
        'run-error'          => 'RTE',
        'timelimit'          => 'TLE',
        'wrong-answer'       => 'WA',
        'no-output'          => 'NO',
        'correct'            => 'AC',
    ],
    'error' => [
        'aborted'            => 'JE',
        'import-error'       => 'IE',
    ],
    'in_progress' => [
        'judging'            => 'JU',
        'pending'            => 'JU',
        'queued'             => 'JU',
    ],
    // The 'external' group is defined in configuration.
];
