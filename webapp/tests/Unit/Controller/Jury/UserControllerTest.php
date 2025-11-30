<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UserControllerTest extends JuryControllerTestCase
{
    protected static string  $identifyingEditAttribute = 'username';
    protected static ?string $defaultEditEntityName    = 'judgehost';
    protected static array   $editEntitiesSkipFields   = ['username'];
    protected static array   $specialFieldOnlyUpdate   = ['plainPassword'];
    protected static string  $baseUrl                  = '/jury/users';
    protected static array   $exampleEntries           = ['admin', 'judgehost', 'Administrator', 'team'];
    protected static string  $shortTag                 = 'user';
    protected static array   $deleteEntities           = ['demo','judgehost'];
    protected static string  $deleteEntityIdentifier   = 'username';
    protected static string  $getIDFunc                = 'getUserid';
    protected static string  $className                = User::class;
    protected static array   $DOM_elements             = ['h1' => ['Users']];
    protected static string  $addForm                  = 'user[';
    protected static array   $addEntitiesShown         = ['name', 'username'];
    protected static array   $overviewSingleNotShown   = ['plainPassword'];
    protected static array   $addEntities              = [['username'      => 'un',
                                                           'name'          => 'Alice',
                                                           'plainPassword' => 'plainpassword',
                                                           'ipAddress'     => '10.0.0.0',
                                                           'enabled'       => '1',
                                                           'team'          => '0',
                                                           'user_roles'    => ['0' => true, '1' => true]],
                                                          ['username' => 'ABCabc123_-@', 'name' => 'Allowed username'],
                                                          ['username' => 'npw', 'name' => 'No password',
                                                           'plainPassword' => ''],
                                                          ['username' => 'specialchar', 'name' => 'Special char in password',
                                                           'plainPassword' => '!@主裁判 судья !"#$%&()*+,-./:;<=>?@[\]^_`{|}~'],
                                                          ['username' => 'quoteinpassword', 'name' => 'quote_in_password',
                                                           'plainPassword' => "pass'w'ord"],
                                                          ['username' => 'nip', 'name' => 'No IP',
                                                           'ipAddress' => ''],
                                                          ['username' => 'ipv6-1', 'name' => 'IPv6-1',
                                                           'ipAddress' => '2001:db8::1234:5678:5.6.7.8'],
                                                          ['username' => 'ipv6-2', 'name' => 'IPv6-2',
                                                           'ipAddress' => '2001:db8:3333:4444:5555:6666:1.2.3.4'],
                                                          ['username' => 'ipv6-3', 'name' => 'IPv6-3',
                                                           'ipAddress' => '2001:0db8:0001:0000:0000:0ab9:C0A8:0102'],
                                                          ['username' => 'ipv6-4', 'name' => 'IPv6-4',
                                                           'ipAddress' => '2001:db8:3333:4444:5555:6666:7777:8888'],
                                                          ['username' => 'ipv6-5', 'name' => 'IPv6-5',
                                                           'ipAddress' => '1:2:3:4:5:6:7:8'],
                                                          ['username' => 'ipv6-6', 'name' => 'IPv6-6',
                                                           'ipAddress' => '::3:4:5:6:7:8'],
                                                          ['username' => 'ipv6-7', 'name' => 'IPv6-7',
                                                           'ipAddress' => '::f'],
                                                          ['username' => 'ipv6-8', 'name' => 'IPv6-8',
                                                           'ipAddress' => 'f::'],
                                                          ['username' => 'disabled', 'name' => 'Disabled',
                                                           'enabled' => '0'],
                                                          ['username' => 'teamless', 'name' => 'Teamless',
                                                           'team' => ''],
                                                          ['username' => 'singlerole-0', 'name' => 'Single Role-0',
                                                           'user_roles' => ['0' => true]],
                                                          ['username' => 'singlerole-1', 'name' => 'Single Role-1',
                                                           'user_roles' => ['1' => true]],
                                                          ['username' => 'singlerole-2', 'name' => 'Single Role-2',
                                                           'user_roles' => ['2' => true]],
                                                          ['username' => 'singlerole-3', 'name' => 'Single Role-3',
                                                           'user_roles' => ['3' => true]],
                                                          ['username' => 'singlerole-4', 'name' => 'Single Role-4',
                                                           'user_roles' => ['4' => true]],
                                                          ['username' => 'singlerole-5', 'name' => 'Single Role-5',
                                                           'user_roles' => ['5' => true]],
                                                          ['username' => 'singlerole-6', 'name' => 'Single Role-6',
                                                           'user_roles' => ['6' => true]],
                                                          ['username' => 'singlerole-7', 'name' => 'Single Role-7',
                                                           'user_roles' => ['7' => true]],
                                                          ['username' => 'singlerole-8', 'name' => 'Single Role-8',
                                                           'user_roles' => ['8' => true]]];
    protected static array   $addEntitiesFailure       = ['Only alphanumeric characters and _-@. are allowed' => [['username' => '|user', 'name' => 'StartWith'],
                                                                                                                  ['username' => 'user|', 'name' => 'EndWith'],
                                                                                                                  ['username' => 'us er', 'name' => 'SpaceUsage'],
                                                                                                                  ['username' => 'usérname', 'name' => 'NonPrintable'],
                                                                                                                  ['username' => 'username⚠', 'name' => 'SpecialSymbol']],
                                                          'This value should not be blank.' => [['username' => '', 'name' => 'Empty']],
                                                          'This value is not a valid IP address.' => [['ipAddress' => '1.1.1'],
                                                                                                      ['ipAddress' => '256.1.1.1'],
                                                                                                      ['ipAddress' => '1.1.1.256'],
                                                                                                      ['ipAddress' => '1.1.1.1.1'],
                                                                                                      ['ipAddress' => '::g']]];

    public function testMultiDeleteUsers(): void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Create some users to delete
        $usersData = [
            ['name' => 'User 1 for multi-delete', 'username' => 'user1md'],
            ['name' => 'User 2 for multi-delete', 'username' => 'user2md'],
            ['name' => 'User 3 for multi-delete', 'username' => 'user3md'],
        ];

        $userIds = [];
        $createdUsers = [];

        foreach ($usersData as $data) {
            $user = new User();
            $user
                ->setName($data['name'])
                ->setUsername($data['username'])
                ->setPlainPassword('password');
            $em->persist($user);
            $createdUsers[] = $user;
        }

        $em->flush();

        // Get the IDs of the newly created users
        foreach ($createdUsers as $user) {
            $userIds[] = $user->getUserid();
        }

        $user1Id = $userIds[0];
        $user2Id = $userIds[1];
        $user3Id = $userIds[2];

        // Verify users exist before deletion
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        foreach ([1, 2, 3] as $i) {
            self::assertSelectorExists(sprintf('body:contains("User %d for multi-delete")', $i));
        }

        // Simulate multi-delete POST request
        $this->client->request(
            'POST',
            static::getContainer()->get('router')->generate('jury_user_delete_multiple', ['ids' => [$user1Id, $user2Id]]),
            [
                'submit' => 'delete'
            ]
        );

        $this->checkStatusAndFollowRedirect();

        // Verify users are deleted
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        self::assertSelectorNotExists('body:contains("User 1 for multi-delete")');
        self::assertSelectorNotExists('body:contains("User 2 for multi-delete")');
        // User 3 should still exist
        self::assertSelectorExists('body:contains("User 3 for multi-delete")');

        // Verify user 3 can still be deleted individually
        $this->verifyPageResponse('GET', static::$baseUrl . '/' . $user3Id . static::$delete, 200);
        $this->client->submitForm('Delete', []);
        $this->checkStatusAndFollowRedirect();
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
    }
}
