<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TeamControllerTest extends BaseTest
{
    protected ?string $apiEndpoint = 'teams';

    protected array $expectedObjects = [
        '2' => [
            'organization_id' => '1',
            'group_ids'       => ['3'],
            'affiliation'     => 'Utrecht University',
            'nationality'     => 'NLD',
            'id'              => '2',
            'icpc_id'         => 'exteam',
            'name'            => 'Example teamname',
            'display_name'    => null,
            'members'         => null,
            'photo'           => null,
        ],
    ];

    protected array $expectedAbsent = ['4242', 'nonexistent'];

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
                'href'     => "contests/1/teams/$id/photo",
                'mime'     => 'image/jpeg',
                'width'    => 320,
                'height'   => 200,
                'filename' => 'photo.jpg',
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
}
