<?php declare(strict_types=1);

namespace App\Utils;

enum EventFeedFormat: string
{
    case Format_2020_03 = '2020-03';
    case Format_2022_07 = '2022-07';

    public function getConfigDescription(): string
    {
        return match ($this) {
            self::Format_2020_03 => 'Legacy format in use until the `2020-03` version',
            self::Format_2022_07 => 'New format in use since the `2022-07` version',
        };
    }
}
