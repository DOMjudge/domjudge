<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\Team;

class TeamControllerTest extends JuryControllerTest
{
    protected static $baseUrl        = '/jury/teams';
    protected static $exampleEntries = ['exteam','DOMjudge','System','UU'];
    protected static $shortTag       = 'team';
    protected static $deleteEntities = ['name' => ['DOMjudge']];
    protected static $getIDFunc      = 'getTeamid';
    protected static $className      = Team::class;
    protected static $DOM_elements   = ['h1' => ['Teams']];
}
