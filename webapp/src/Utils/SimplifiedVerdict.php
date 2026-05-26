<?php declare(strict_types=1);

namespace App\Utils;

enum SimplifiedVerdict: string
{
    case AC = 'AC';
    case CE = 'CE';
    case RE = 'RE';

    public static function for(string $verdictLabel): self
    {
        return match ($verdictLabel) {
            self::AC->value => self::AC,
            self::CE->value => self::CE,
            default         => self::RE,
        };
    }
}
