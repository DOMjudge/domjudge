<?php declare(strict_types=1);

namespace App\DataTransferObject;

enum ImageTag: string
{
    case LIGHT = 'light';
    case DARK = 'dark';
}
