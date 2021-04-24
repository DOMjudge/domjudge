<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\TeamAffiliation;
use Generator;

class TeamAffiliationControllerTest extends JuryControllerTest
{
    protected static $baseUrl        = '/jury/affiliations';
    protected static $exampleEntries = ['UU','Utrecht University',1];
    protected static $shortTag       = 'affiliation';
    protected static $deleteEntities = ['shortname' => ['UU']];
    protected static $getIDFunc      = 'getAffilid';
    protected static $className      = TeamAffiliation::class;
    protected static $DOM_elements   = ['h1' => ['Affiliations']];
    protected static $addFormName    = 'team_affiliation';
}
