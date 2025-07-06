<?php declare(strict_types=1);

namespace App\Utils;

enum CcsApiVersion: string
{
    case Format_2020_03 = '2020-03';
    case Format_2023_06 = '2023-06';
    case Format_2025_DRAFT = '2025-draft';

    public function getConfigDescription(): string
    {
        return match ($this) {
            self::Format_2020_03 => '`2020-03` version',
            self::Format_2023_06 => '`2023-06` version, backwards compatible with `2022-07`',
            self::Format_2025_DRAFT => '`2025-draft` version',
        };
    }

    public function getCcsSpecsApiUrl(): string
    {
        return match ($this) {
            self::Format_2025_DRAFT => 'https://ccs-specs.icpc.io/draft/contest_api',
            default => sprintf('https://ccs-specs.icpc.io/%s/contest_api', $this->value)
        };
    }

    public function useRelTimes(): bool
    {
        return match ($this) {
            self::Format_2020_03, self::Format_2023_06 => false,
            default => true,
        };
    }
}
