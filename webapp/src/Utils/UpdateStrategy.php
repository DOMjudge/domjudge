<?php declare(strict_types=1);

namespace App\Utils;

enum UpdateStrategy: string
{
    case Strategy_incremental = 'incremental';
    case Strategy_major_release = 'major';
    case Strategy_none = 'none';

    public function getConfigDescription(): string
    {
        return match ($this) {
            self::Strategy_incremental => 'Report on next patch releases, favoring reliability over features',
            self::Strategy_major_release => 'Report on newest Major/minor releases, favoring being close to the version maintainers run',
            self::Strategy_none => 'Do not report on any new versions',
        };
    }
}
