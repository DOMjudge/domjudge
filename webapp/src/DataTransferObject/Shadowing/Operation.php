<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

enum Operation: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
}
