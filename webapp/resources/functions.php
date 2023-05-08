<?php declare(strict_types=1);

use App\Utils\Adminer;

if (!function_exists('adminer_object')) {
    function adminer_object()
    {
        return new Adminer();
    }
}
