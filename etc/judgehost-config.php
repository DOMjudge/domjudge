<?php declare(strict_types=1);

// Log to syslog facility; do not define to disable.
define('SYSLOG', LOG_LOCAL0);

// Normally submissions don't have any directory they can write to,
// at least when the chroot is enabled. However, some languages might
// require a temporary (writable) directory to put stuff in (for example
// R). Setting this to true will create a directory "write_tmp" where
// the submission can write to. Also the environment variable TMPDIR will
// be set to this directory
define('CREATE_WRITABLE_TEMP_DIR', getenv('DOMJUDGE_CREATE_WRITABLE_TEMP_DIR') ? true : false);

// These define HTTP request backoff related constants.
// If any transient network error occurs on the nth trial,
// the judgehost retries the HTTP request after pow(factor, trial - 1) + rand(0, jitter) sec.

function define_backoff_params_from_env(string $var_name, $default_value)
{
    if (defined($var_name)) {
        return;
    }
    $options = array(
        'options' => array(
            'default' => $default_value,
            'min_range' => 0,
        ),
    );
    $final_value = filter_var(getenv('DOMJUDGE_' . $var_name), FILTER_VALIDATE_FLOAT, $options);
    define($var_name, $final_value);
}

define_backoff_params_from_env('BACKOFF_JITTER_SEC', 0.2);
define_backoff_params_from_env('BACKOFF_FACTOR', 2);
define_backoff_params_from_env('BACKOFF_STEPS', 3);
define_backoff_params_from_env('BACKOFF_INITIAL_DELAY_SEC', 1.0);
