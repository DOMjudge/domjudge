<?php declare(strict_types=1);

// Mapping of DOMjudge verdict strings to those defined in the CLICS
// CCS specification (and a few more common ones) at:
// https://clics.ecs.baylor.edu/index.php/Contest_Control_System#Judge_Responses
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
];
