<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\NonSortOrderTeamCategoryFixture;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

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
    protected static string  $getIDFunc                = 'getExternalid';
    protected static string  $className                = Team::class;
    protected static array   $DOM_elements             = ['h1' => ['Teams']];
    protected static string  $addForm                  = 'team[';
    protected static array   $addEntitiesShown         = ['icpcid', 'label', 'displayName', 'location'];
    protected static array   $overviewSingleNotShown   = ['addUserForTeam'];
    protected static array   $overviewGeneralNotShown  = ['icpcid'];
    protected static array   $addEntitiesCount         = ['contests'];
    protected static array   $addEntities              = [['name' => 'New Team',
                                                           'displayName' => 'New Team Display Name',
                                                           'categories' => ['participants'],
                                                           'publicdescription' => 'Some members',
                                                           'penalty' => '0',
                                                           'location' => 'The first room',
                                                           'internalcomments' => 'This is a team without a user',
                                                           'contests' => [],
                                                           'enabled' => true,
                                                           'addUserForTeam' => Team::DONT_ADD_USER,
                                                           'icpcid' => ''],
                                                          ['name' => 'Another Team',
                                                           'displayName' => 'Another Team Display Name',
                                                           'categories' => ['system'],
                                                           'publicdescription' => 'More members',
                                                           'penalty' => '20',
                                                           'location' => 'Another room',
                                                           'internalcomments' => 'This is a team with a user',
                                                           'enabled' => true,
                                                           'addUserForTeam' => Team::CREATE_NEW_USER,
                                                           'newUsername' => 'linkeduser'],
                                                          ['name' => 'Team linked to existing user',
                                                           'displayName' => 'Third team display name',
                                                           'categories' => ['system'],
                                                           'publicdescription' => 'Members of this team',
                                                           'penalty' => '0',
                                                           'enabled' => true,
                                                           'addUserForTeam' => Team::ADD_EXISTING_USER,
                                                           'existingUser' => 'demo'],
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

    public function testMultiDeleteTeams(): void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Create some teams to delete
        $teamsData = [
            ['name' => 'Team 1 for multi-delete'],
            ['name' => 'Team 2 for multi-delete'],
            ['name' => 'Team 3 for multi-delete'],
        ];

        $teamIds = [];
        $createdTeams = [];

        foreach ($teamsData as $data) {
            $team = new Team();
            $team->setName($data['name']);
            $em->persist($team);
            $createdTeams[] = $team;
        }

        $em->flush();

        // Get the IDs of the newly created teams
        foreach ($createdTeams as $team) {
            $teamIds[] = $team->getExternalid();
        }

        $team1Id = $teamIds[0];
        $team2Id = $teamIds[1];
        $team3Id = $teamIds[2];

        // Verify teams exist before deletion
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        foreach ([1, 2, 3] as $i) {
            self::assertSelectorExists(sprintf('body:contains("Team %d for multi-delete")', $i));
        }

        // Simulate multi-delete POST request
        $this->client->request(
            'POST',
            static::getContainer()->get('router')->generate('jury_team_delete_multiple', ['ids' => [$team1Id, $team2Id]]),
            [
                'submit' => 'delete'
            ]
        );

        $this->checkStatusAndFollowRedirect();

        // Verify teams are deleted
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        self::assertSelectorNotExists('body:contains("Team 1 for multi-delete")');
        self::assertSelectorNotExists('body:contains("Team 2 for multi-delete")');
        // Team 3 should still exist
        self::assertSelectorExists('body:contains("Team 3 for multi-delete")');

        // Verify team 3 can still be deleted individually
        $this->verifyPageResponse('GET', static::$baseUrl . '/' . $team3Id . static::$delete, 200);
        $this->client->submitForm('Delete', []);
        $this->checkStatusAndFollowRedirect();
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
    }

    /**
     * Test that adding a team without a user and then editing it to add a user works.
     */
    public function testAddWithoutUserThenEdit(): void
    {
        $teamToAdd = static::$addEntities[0];
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        $this->helperSubmitFields($teamToAdd);
        $viewPage = $this->checkStatusAndFollowRedirect()->getUri();
        $editPage = $viewPage . static::$edit;
        $this->verifyPageResponse('GET', $editPage, 200);
        $formFields = [
            static::$addForm . 'addUserForTeam]' => Team::CREATE_NEW_USER,
            static::$addForm . 'newUsername]' => 'somelinkeduser',
        ];
        $button = $this->client->getCrawler()->selectButton('Save');
        $form = $button->form($formFields, 'POST');
        $this->client->submit($form);
        self::assertNotEquals(500, $this->client->getResponse()->getStatusCode());

        /** @var EntityManagerInterface $em */
        $em = $this->getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['username' => 'somelinkeduser']);

        static::assertNotNull($user);
        static::assertEquals('New Team', $user->getTeam()->getName());
    }

    /**
     * Test that adding a team with multiple categories works.
     */
    public function testAddMultipleCategories(): void
    {
        $this->loadFixture(NonSortOrderTeamCategoryFixture::class);
        $teamToAdd = static::$addEntities[0];
        $teamToAdd['categories'][] = $this->resolveReference(NonSortOrderTeamCategoryFixture::class . ':0', TeamCategory::class, preferExternalId: true);
        [$combinedValues, $element] = $this->helperProvideMergeAddEntity($teamToAdd);
        [$combinedValues, $element] = $this->helperProvideTranslateAddEntity($combinedValues, $element);
        $this->testCheckAddEntityAdmin($combinedValues, $element);
    }
}
