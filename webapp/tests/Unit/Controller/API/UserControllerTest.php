<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\SelfRegisteredUserFixture;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UserControllerTest extends AccountBaseTestCase
{
    protected ?string $apiEndpoint = 'users';

    protected array $expectedObjects = [
        'admin' => [
            "team" => "DOMjudge",
            "roles" => [
                "admin",
                "team"
            ],
            "id" => "admin",
            "username" => "admin",
            "name" => "Administrator",
            "ip" => null,
            "enabled" => true
        ],
        'judgehost' => [
            "team" => null,
            "roles" => [
                "judgehost"
            ],
            "id" => "judgehost",
            "username" => "judgehost",
            "name" => "User for judgedaemons",
            "ip" => null,
            "enabled" => true
        ],
        'demo' => [
            "team" => "Example teamname",
            "roles" => [
                 "team"
            ],
            "id" => "demo",
            "username" => "demo",
            "name" => "demo user for example team",
            "ip" => null,
            "enabled" => true
        ],
    ];

    public function testAddLocal(): void
    {
        $data = [
            'username' => 'testuser',
            'name' => 'Test User',
            'roles' => ['team'],
            'password' => 'testpassword',
        ];

        $response = $this->verifyApiJsonResponse('POST', $this->helperGetEndpointURL($this->apiEndpoint), 201, 'admin', $data);
        static::assertArrayHasKey('id', $response);
        static::assertEquals('testuser', $response['username']);
        static::assertEquals('Test User', $response['name']);
        static::assertEquals(['team'], $response['roles']);
    }

    public function testUpdate(): void
    {
        $data = [
            'id' => 'someid',
            'username' => 'testuser',
            'name' => 'Test User',
            'roles' => ['team'],
            'password' => 'testpassword',
        ];

        $response = $this->verifyApiJsonResponse('PUT', $this->helperGetEndpointURL($this->apiEndpoint) . '/someid', 201, 'admin', $data);
        static::assertEquals('someid', $response['id']);
        static::assertEquals('testuser', $response['username']);
        static::assertEquals('Test User', $response['name']);
        static::assertEquals(['team'], $response['roles']);
    }

    public function testUpdateReplacesAllFields(): void
    {
        $user = $this->prepareSelfRegisteredUser();
        $password = $user->getPassword();

        $data = [
            'id' => 'selfregister',
            'username' => 'selfregister',
            'name' => 'Test User',
            'roles' => ['balloon'],
        ];

        $response = $this->verifyApiJsonResponse('PUT', $this->helperGetEndpointURL($this->apiEndpoint) . '/selfregister', 200, 'admin', $data);
        static::assertEquals('Test User', $response['name']);

        $user = $this->reloadUser($user);
        static::assertNotEmpty($password);
        static::assertEquals($password, $user->getPassword(), 'PUT must not lock out an existing user');
        static::assertNull($user->getIpAddress());
        static::assertNull($user->getTeam());
        static::assertTrue($user->getEnabled());
        static::assertEquals(['balloon'], $user->getRoleList());
    }

    public function testUpdateWithoutRoles(): void
    {
        $data = [
            'id' => 'someid',
            'username' => 'testuser',
            'name' => 'Test User',
        ];

        $this->verifyApiJsonResponse('PUT', $this->helperGetEndpointURL($this->apiEndpoint) . '/someid', 400, 'admin', $data);
    }

    public function testPatchKeepsOtherFields(): void
    {
        $user = $this->prepareSelfRegisteredUser();
        $password = $user->getPassword();

        $response = $this->verifyApiJsonResponse('PATCH', $this->helperGetEndpointURL($this->apiEndpoint) . '/selfregister', 200, 'admin', ['name' => 'Test User']);
        static::assertEquals('Test User', $response['name']);

        $user = $this->reloadUser($user);
        static::assertEquals($password, $user->getPassword());
        static::assertEquals('10.0.0.1', $user->getIpAddress());
        static::assertEquals('exteam', $user->getTeam()->getExternalid());
        static::assertFalse($user->getEnabled());
        static::assertEquals(['team'], $user->getRoleList());
    }

    public function testPatchClearsNullFields(): void
    {
        $user = $this->prepareSelfRegisteredUser();

        $data = ['ip' => null, 'team_id' => null, 'password' => null];
        $this->verifyApiJsonResponse('PATCH', $this->helperGetEndpointURL($this->apiEndpoint) . '/selfregister', 200, 'admin', $data);

        $user = $this->reloadUser($user);
        static::assertEmpty($user->getPassword());
        static::assertNull($user->getIpAddress());
        static::assertNull($user->getTeam());
        static::assertEquals('selfregistered user for example team', $user->getName());
    }

    public function testPatchNotFound(): void
    {
        $this->verifyApiJsonResponse('PATCH', $this->helperGetEndpointURL($this->apiEndpoint) . '/nonexistent', 404, 'admin', ['name' => 'Test User']);
    }

    public function testPatchIdMismatch(): void
    {
        static::loadFixture(SelfRegisteredUserFixture::class);

        $data = ['id' => 'otherid', 'name' => 'Test User'];
        $response = $this->verifyApiJsonResponse('PATCH', $this->helperGetEndpointURL($this->apiEndpoint) . '/selfregister', 400, 'admin', $data);
        static::assertEquals('ID in URL does not match ID in payload', $response['message']);
    }

    private function prepareSelfRegisteredUser(): User
    {
        static::loadFixture(SelfRegisteredUserFixture::class);

        /** @var EntityManagerInterface $manager */
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var User $user */
        $user = $manager->getRepository(User::class)->findOneBy(['username' => 'selfregister']);
        $user
            ->setEnabled(false)
            ->setIpAddress('10.0.0.1')
            ->setTeam($manager->getRepository(Team::class)->findOneBy(['externalid' => 'exteam']));
        $manager->flush();

        return $user;
    }

    private function reloadUser(User $user): User
    {
        /** @var EntityManagerInterface $manager */
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        $manager->clear();

        return $manager->getRepository(User::class)->find($user->getUserid());
    }

    public function testUpdateNoId(): void
    {
        $data = [
            'username' => 'testuser',
            'name' => 'Test User',
            'roles' => ['team'],
            'password' => 'testpassword',
        ];

        $response = $this->verifyApiJsonResponse('PUT', $this->helperGetEndpointURL($this->apiEndpoint) . '/someid', 400, 'admin', $data);
        static::assertMatchesRegularExpression('/id:\n.*This value should be of type string./', $response['message']);
    }

    public function testUpdateWithInvalidExternalId(): void
    {
        $data = [
            'id' => 'invalid id!',
            'username' => 'testuser',
            'name' => 'Test User',
            'roles' => ['team'],
            'password' => 'testpassword',
        ];

        $response = $this->verifyApiJsonResponse('PUT', $this->helperGetEndpointURL($this->apiEndpoint) . '/invalid%20id!', 400, 'admin', $data);
        self::assertStringContainsString('Only letters, numbers, dashes, underscores and dots are allowed.', $response['message']);
    }

    public function testChangePasswordDisallowed(): void
    {
        $data = [
            'current_password' => 'demo',
            'new_password' => 'new-secret-password-123',
        ];

        // By default, demo user cannot change password unless category or role allows it
        $this->verifyApiJsonResponse('POST', $this->helperGetEndpointURL($this->apiEndpoint) . '/demo/change-password', 403, 'demo', $data);
    }

    public function testChangePasswordOtherUserForbidden(): void
    {
        $data = [
            'current_password' => 'demo',
            'new_password' => 'new-secret-password-123',
        ];

        // demo cannot change admin's password
        $this->verifyApiJsonResponse('POST', $this->helperGetEndpointURL($this->apiEndpoint) . '/admin/change-password', 403, 'demo', $data);
    }

    public function testChangePasswordMissingFields(): void
    {
        $this->verifyApiJsonResponse('POST', $this->helperGetEndpointURL($this->apiEndpoint) . '/demo/change-password', 400, 'demo', []);
    }

    public function testChangePasswordUnknownUser(): void
    {
        $data = [
            'current_password' => 'demo',
            'new_password' => 'new-secret-password-123',
        ];

        $this->verifyApiJsonResponse('POST', $this->helperGetEndpointURL($this->apiEndpoint) . '/nonexistent/change-password', 404, 'demo', $data);
    }

    public function testChangePasswordWrongCurrentPassword(): void
    {
        $this->allowPasswordChangeForDemoCategory();

        $data = [
            'current_password' => 'not-the-password',
            'new_password' => 'new-secret-password-123',
        ];

        $response = $this->verifyApiJsonResponse('POST', $this->helperGetEndpointURL($this->apiEndpoint) . '/demo/change-password', 400, 'demo', $data);
        self::assertStringContainsString('Current password is incorrect.', $response['message']);
    }

    public function testChangePasswordNewPasswordTooShort(): void
    {
        $this->allowPasswordChangeForDemoCategory();

        $data = [
            'current_password' => 'demo',
            'new_password' => 'x',
        ];

        $response = $this->verifyApiJsonResponse('POST', $this->helperGetEndpointURL($this->apiEndpoint) . '/demo/change-password', 400, 'demo', $data);
        self::assertStringContainsString('at least', $response['message']);
    }

    public function testChangePasswordSuccess(): void
    {
        $this->allowPasswordChangeForDemoCategory();

        /** @var EntityManagerInterface $manager */
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        $oldHash = $manager->getRepository(User::class)->findOneBy(['username' => 'demo'])->getPassword();

        $data = [
            'current_password' => 'demo',
            'new_password' => 'new-secret-password-123',
        ];

        $this->verifyApiJsonResponse('POST', $this->helperGetEndpointURL($this->apiEndpoint) . '/demo/change-password', 204, 'demo', $data);

        $manager->clear();
        $newHash = $manager->getRepository(User::class)->findOneBy(['username' => 'demo'])->getPassword();
        self::assertNotEquals($oldHash, $newHash);
    }

    private function allowPasswordChangeForDemoCategory(): void
    {
        /** @var EntityManagerInterface $manager */
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var User $user */
        $user = $manager->getRepository(User::class)->findOneBy(['username' => 'demo']);
        foreach ($user->getTeam()->getCategories() as $category) {
            $category->setAllowPasswordChange(true);
        }
        $manager->flush();
    }
}
