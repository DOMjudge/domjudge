<?php declare(strict_types=1);

namespace App\Entity;

enum ScoreboardType: string
{
    case PASS_FAIL = 'pass-fail';
    case SCORE     = 'score';
}
