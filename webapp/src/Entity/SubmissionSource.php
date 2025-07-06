<?php declare(strict_types=1);

namespace App\Entity;

enum SubmissionSource: string
{
    case API = 'API';
    case EDIT_RESUBMIT = 'edit/resubmit';
    case PROBLEM_IMPORT = 'problem import';
    case SHADOWING = 'shadowing';
    case TEAM_PAGE = 'team page';
    case UNKNOWN = 'unknown';
}
