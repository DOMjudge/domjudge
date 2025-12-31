<?php declare(strict_types=1);

namespace App\Entity;

enum ExternalContestSourceType: string
{
    case CCS_API = 'ccs-api';
    case CONTEST_PACKAGE = 'contest-archive';

    public function readable(): string
    {
        return match ($this) {
            self::CCS_API => 'CCS API (URL)',
            self::CONTEST_PACKAGE => 'Contest package (directory)',
        };
    }
}
