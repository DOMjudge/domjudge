<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\AddLocationToTeamFixture;
use App\DataFixtures\Test\CreateTeamWithTwoTeamAffiliationsFixture;
use App\Entity\Team;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TeamControllerTest extends BaseTestCase
{
    protected ?string $apiEndpoint = 'teams';

    protected array $expectedObjects = [
        'exteam' => [
            'organization_id' => 'utrecht',
            'group_ids'       => ['participants'],
            'affiliation'     => 'Utrecht University',
            'nationality'     => 'NLD',
            'id'              => 'exteam',
            'icpc_id'         => 'exteam',
            'name'            => 'Example teamname',
            'display_name'    => null,
            'members'         => null,
            'photo'           => null,
            'location'        => ['description' => 'Utrecht'],
        ],
        'teamwithtwogroups' => [
            'group_ids'       => ['participants', 'observers'],
            'affiliation'     => 'Utrecht University',
            'nationality'     => 'NLD',
            'id'              => 'teamwithtwogroups',
            'icpc_id'         => 'teamwithtwogroups',
            'name'            => 'Team with two groups',
            'display_name'    => null,
            'members'         => null,
            'photo'           => null,
        ],
    ];

    protected static array $fixtures = [
        AddLocationToTeamFixture::class,
        CreateTeamWithTwoTeamAffiliationsFixture::class,
    ];

    protected array $expectedAbsent = ['4242', 'nonexistent'];

    protected ?string $objectClassForExternalId = Team::class;

    // Writes the photo of a fixed team ID to the shared public/ dir.
    #[Group('no-parallel')]
    public function testLogoManagement(): void
    {
        // Note: we are doing this as admin as we require privileges

        // First, make sure we have no photo
        $id = 2;
        if ($this->objectClassForExternalId !== null) {
            $id = $this->resolveEntityId($this->objectClassForExternalId, (string)$id);
        }
        $url = $this->helperGetEndpointURL($this->apiEndpoint, (string)$id);
        $object = $this->verifyApiJsonResponse('GET', $url, 200, 'admin');
        self::assertArrayNotHasKey('photo', $object);

        // Now upload a photo
        $photoFile = __DIR__ . '/../../../../public/images/teams/domjudge.jpg';
        $photo = new UploadedFile($photoFile, 'domjudge.jpg');
        $this->verifyApiJsonResponse('POST', $url . '/photo', 204, 'admin', null, ['photo' => $photo]);

        // Verify we do have a photo now
        $object = $this->verifyApiJsonResponse('GET', $url, 200, 'admin');
        $logoConfig = [
            [
                'href'     => "contests/demo/teams/$id/photo",
                'mime'     => 'image/jpeg',
                'filename' => 'photo.jpg',
                'tags'     => ['light'],
                'width'    => 320,
                'height'   => 200,
            ]
        ];
        self::assertSame($logoConfig, $object['photo']);

        $this->client->request('GET', '/api' . $url . '/photo');
        /** @var BinaryFileResponse $response */
        $response = $this->client->getResponse();
        self::assertFileEquals($photoFile, $response->getFile()->getRealPath());

        // Delete the logo again
        $this->verifyApiJsonResponse('DELETE', $url . '/photo', 204, 'admin');

        // Verify we have no banner anymore
        $object = $this->verifyApiJsonResponse('GET', $url, 200, 'admin');
        self::assertArrayNotHasKey('photo', $object);
    }

    public function testNewAddedTeamWithInvalidId(): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint);
        $data = ['id' => 'invalid id!', 'name' => 'Test Team', 'group_ids' => ['participants']];
        $response = $this->verifyApiJsonResponse('POST', $url, 400, 'admin', $data);
        self::assertStringContainsString('Only letters, numbers, dashes, underscores and dots are allowed.', $response['message']);
    }

    public function testAddExistingTeam(): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint);
        $data = ['id' => 'exteam', 'name' => 'Other name', 'group_ids' => ['participants']];
        $response = $this->verifyApiJsonResponse('POST', $url, 400, 'admin', $data);
        self::assertEquals("Team with ID 'exteam' already exists", $response['message']);
    }

    public function testPutNewTeam(): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint, 'newteam');
        $data = ['id' => 'newteam', 'name' => 'New team', 'group_ids' => ['participants']];
        $response = $this->verifyApiJsonResponse('PUT', $url, 201, 'admin', $data);
        self::assertEquals('newteam', $response['id']);
        self::assertEquals('New team', $response['name']);
    }

    public function testPutReplacesTeam(): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint, 'exteam');
        $data = ['id' => 'exteam', 'name' => 'Replaced name', 'group_ids' => ['observers']];
        $response = $this->verifyApiJsonResponse('PUT', $url, 200, 'admin', $data);
        self::assertEquals('Replaced name', $response['name']);
        self::assertEquals(['observers'], $response['group_ids']);
        self::assertNull($response['icpc_id']);
        self::assertNull($response['organization_id']);
        self::assertNull($response['location']);
    }

    public function testPutIdMismatch(): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint, 'exteam');
        $data = ['id' => 'otherid', 'name' => 'Replaced name'];
        $response = $this->verifyApiJsonResponse('PUT', $url, 400, 'admin', $data);
        self::assertEquals('ID in URL does not match ID in payload', $response['message']);
    }

    public function testPatchKeepsOtherFields(): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint, 'exteam');
        $response = $this->verifyApiJsonResponse('PATCH', $url, 200, 'admin', ['display_name' => 'Patched']);
        self::assertEquals('Patched', $response['display_name']);
        self::assertEquals('Example teamname', $response['name']);
        self::assertEquals('exteam', $response['icpc_id']);
        self::assertEquals(['participants'], $response['group_ids']);
        self::assertEquals('utrecht', $response['organization_id']);
        self::assertEquals(['description' => 'Utrecht'], $response['location']);
    }

    public function testPatchClearsNullFields(): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint, 'exteam');
        $response = $this->verifyApiJsonResponse('PATCH', $url, 200, 'admin', ['organization_id' => null, 'location' => null]);
        self::assertEquals('Example teamname', $response['name']);
        self::assertNull($response['organization_id']);
        self::assertNull($response['location']);
    }

    public function testPatchNotFound(): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint, 'nonexistent');
        $this->verifyApiJsonResponse('PATCH', $url, 404, 'admin', ['name' => 'Patched']);
    }
}
