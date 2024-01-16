<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\Team;

class TeamControllerTest extends JuryControllerTestCase
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
    protected static array   $addEntitiesShown         = ['icpcid', 'label', 'displayName', 'location'];
    protected static array   $overviewSingleNotShown   = ['addUserForTeam'];
    protected static array   $overviewGeneralNotShown  = ['icpcid'];
    protected static array   $addEntitiesCount         = ['contests'];
    protected static array   $addEntities              = [['name' => 'New Team',
                                                           'displayName' => 'New Team Display Name',
                                                           'category' => '3',
                                                           'publicdescription' => 'Some members',
                                                           'penalty' => '0',
                                                           'location' => 'The first room',
                                                           'internalcomments' => 'This is a team without a user',
                                                           'contests' => [],
                                                           'enabled' => '1',
                                                           'addUserForTeam' => Team::DONT_ADD_USER,
                                                           'icpcid' => ''],
                                                          ['name' => 'Another Team',
                                                           'displayName' => 'Another Team Display Name',
                                                           'category' => '1',
                                                           'publicdescription' => 'More members',
                                                           'penalty' => '20',
                                                           'location' => 'Another room',
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
                                                           'label' => 'teamlabel',
                                                           'displayName' => 'With External ID'],
                                                          ['name' => 'no_members',
                                                           'publicdescription' => '',
                                                           'displayName' => 'Team without members'],
                                                          ['name' => 'no_location',
                                                           'location' => '',
                                                           'displayName' => 'Team without a location'],
                                                          ['name' => 'no_comments',
                                                           'internalcomments' => '',
                                                           'displayName' => 'Team without comments'],
                                                          ['name' => 'no_contests',
                                                           'contests' => [],
                                                           'displayName' => 'Team without contests'],
                                                          ['name' => 'icpc team (string)',
                                                           'icpcid' => 'eleven'],
                                                          ['name' => 'icpc team (number)',
                                                           'icpcid' => '11'],
                                                          ['name' => 'icpc team (alpha-_)',
                                                           'icpcid' => 'abc-ABC_123'],
                                                          ['name' => 'Headstart',
                                                           'penalty' => '-20'],
                                                          ['name' => 'Empty displayname',
                                                           'displayName' => '']];
    protected static array   $addEntitiesFailure       = ['May only contain [a-zA-Z0-9_-].' => [['addUserForTeam' => Team::CREATE_NEW_USER, 'newUsername' => '|user', 'name' => 'StartWith'],
                                                                                                ['addUserForTeam' => Team::CREATE_NEW_USER, 'newUsername' => 'user|', 'name' => 'EndWith'],
                                                                                                ['addUserForTeam' => Team::CREATE_NEW_USER, 'newUsername' => 'us er', 'name' => 'SpaceUsage'],
                                                                                                ['addUserForTeam' => Team::CREATE_NEW_USER, 'newUsername' => 'usérname', 'name' => 'NonPrintable'],
                                                                                                ['addUserForTeam' => Team::CREATE_NEW_USER, 'newUsername' => 'username⚠', 'name' => 'SpecialSymbol']],
                                                          'Only alphanumeric characters and _-@. are allowed' => [['addUserForTeam' => Team::CREATE_NEW_USER, 'newUsername' => '|user'],
                                                                                                                  ['addUserForTeam' => Team::CREATE_NEW_USER, 'newUsername' => 'user|'],
                                                                                                                  ['addUserForTeam' => Team::CREATE_NEW_USER, 'newUsername' => 'us er'],
                                                                                                                  ['addUserForTeam' => Team::CREATE_NEW_USER, 'newUsername' => 'usérname'],
                                                                                                                  ['addUserForTeam' => Team::CREATE_NEW_USER, 'newUsername' => 'username⚠']],
                                                          'Required when adding a user' => [['addUserForTeam' => Team::CREATE_NEW_USER,
                                                                                             'newUsername' => '']],
                                                          'Only letters, numbers, dashes and underscores are allowed.' => [['icpcid' => '|viol', 'name' => 'icpcid violation-1'],
                                                                                                                           ['icpcid' => '&viol', 'name' => 'icpcid violation-2']],
                                                          'This value should not be blank.' => [['name' => '', 'displayName' => 'Teams should have a name']]];
}
