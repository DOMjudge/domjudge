<?php declare(strict_types=1);

namespace App\Utils;

enum UpdateStrategy: string
{
    case INCREMENTAL = 'incremental';
    case MAJOR_RELEASE = 'major';
    case NONE = 'none';

    public function getConfigDescription(): string
    {
        return match ($this) {
            self::INCREMENTAL => 'Report on next patch releases, favoring reliability over features',
            self::MAJOR_RELEASE => 'Report on newest Major/minor releases, favoring being close to the version maintainers run',
            self::NONE => 'Do not report on any new versions',
        };
    }
}
