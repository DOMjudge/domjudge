<?php declare(strict_types=1);

namespace App\Service\Compare;

enum MessageType: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';
}
