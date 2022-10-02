<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\Team;

class TeamControllerTest extends JuryControllerTest
{
    protected static string  $identifyingEditAttribute = 'name';
    protected static ?string $defaultEditEntityName    = 'DOMjudge';
    protected static array   $editEntitiesSkipFields   = ['addUserForTeam', 'users.0.username'];
    protected static string  $baseUrl                  = '/jury/teams';
    protected static array   $exampleEntries           = ['exteam', 'DOMjudge', 'System', 'UU'];
    protected static string  $shortTag                 = 'team';
    protected static array   $deleteEntities           = ['DOMjudge','Example teamname'];
    protected static string  $deleteEntityIdentifier   = 'name';
    protected static string  $getIDFunc                = 'getTeamid';
    protected static string  $className                = Team::class;
    protected static array   $DOM_elements             = ['h1' => ['Teams']];
    protected static string  $addForm                  = 'team[';
    protected static array   $addEntitiesShown         = ['icpcid', 'displayName', 'room'];
    protected static array   $overviewNotShown         = ['addUserForTeam'];
    protected static array   $addEntitiesCount         = ['contests'];
    protected static array   $addEntities              = [['name' => 'New Team',
                                                           'displayName' => 'New Team Display Name',
                                                           'category' => '3',
                                                           'publicdescription' => 'Some members',
                                                           'penalty' => '0',
                                                           'room' => 'The first room',
                                                           'internalcomments' => 'This is a team without a user',
                                                           'contests' => [],
                                                           'enabled' => '1',
                                                           'addUserForTeam' => Team::DONT_ADD_USER],
                                                          ['name' => 'Another Team',
                                                           'displayName' => 'Another Team Display Name',
                                                           'category' => '1',
                                                           'publicdescription' => 'More members',
                                                           'penalty' => '20',
                                                           'room' => 'Another room',
                                                           'internalcomments' => 'This is a team with a user',
                                                           'enabled' => '1',
                                                           'addUserForTeam' => Team::CREATE_NEW_USER,
                                                           'newUsername' => 'linkeduser'],
                                                          ['name' => 'Team linked to existing user',
                                                           'displayName' => 'Third team display name',
                                                           'category' => '1',
                                                           'publicdescription' => 'Members of this team',
                                                           'penalty' => '0',
                                                           'enabled' => '1',
                                                           'addUserForTeam' => Team::ADD_EXISTING_USER,
                                                           'existingUser' => 3],
                                                          ['name' => 'external_ID',
                                                          'icpcid' => '12',
                                                          'displayName' => 'With External ID'],
                                                          ['name' => 'no_members',
                                                          'publicdescription' => '',
                                                          'displayName' => 'Team without members'],
                                                          ['name' => 'no_room',
                                                          'room' => '',
                                                          'displayName' => 'Team without a room'],
                                                          ['name' => 'no_comments',
                                                          'internalcomments' => '',
                                                          'displayName' => 'Team without comments'],
                                                          ['name' => 'no_contests',
                                                          'contests' => [],
                                                          'displayName' => 'Team without contests'],
                                                         ];
}
