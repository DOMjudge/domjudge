<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\Team;

class TeamControllerTest extends JuryControllerTest
{
    protected static $identifyingEditAttribute = 'name';
    protected static $defaultEditEntityName    = 'DOMjudge';
    protected static $editEntitiesSkipFields   = ['addUserForTeam','users.0.username'];
    protected static $baseUrl          = '/jury/teams';
    protected static $exampleEntries   = ['exteam','DOMjudge','System','UU'];
    protected static $shortTag         = 'team';
    protected static $deleteEntities   = ['name' => ['DOMjudge']];
    protected static $getIDFunc        = 'getTeamid';
    protected static $className        = Team::class;
    protected static $DOM_elements     = ['h1' => ['Teams']];
    protected static $addForm          = 'team[';
    protected static $addEntitiesShown = ['name','icpcid','displayName','room'];
    protected static $addEntitiesCount = ['contests'];
    protected static $addEntities      = [['name' => 'New Team',
                                           'displayName' => 'New Team Display Name',
                                           'category' => '3',
                                           'members' => 'Some members',
                                           'penalty' => '0',
                                           'room' => 'The first room',
                                           'comments' => 'This is a team without a user',
                                           'contests' => ['1'],
                                           'enabled' => '1',
                                           'addUserForTeam' => false],
                                          ['name' => 'Another Team',
                                           'displayName' => 'Another Team Display Name',
                                           'category' => '1',
                                           'members' => 'More members',
                                           'penalty' => '20',
                                           'room' => 'Another room',
                                           'comments' => 'This is a team with a user',
                                           'enabled' => '1',
                                           'addUserForTeam' => true,
                                           'users.0.username' => 'linkeduser'],
                                          ['name' => 'external_ID',
                                           'icpcid' => '12',
                                           'displayName' => 'With External ID'],
                                          ['name' => 'no_members',
                                           'members' => '',
                                           'displayName' => 'Team without members'],
                                          ['name' => 'no_room',
                                           'room' => '',
                                           'displayName' => 'Team without a room'],
                                          ['name' => 'no_comments',
                                           'comments' => '',
                                           'displayName' => 'Team without comments'],
                                          ['name' => 'no_contests',
                                           'contests' => [],
                                           'displayName' => 'Team without contests'],
                                        ];
}
