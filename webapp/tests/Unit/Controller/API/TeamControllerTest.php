<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class TeamControllerTest extends BaseTest
{
    protected $apiEndpoint = 'teams';

    protected $expectedObjects = [
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

    protected $expectedAbsent = ['4242', 'nonexistent'];

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
        $photo = new UploadedFile(__DIR__ . '/../../../../public/images/teams/domjudge.jpg', 'domjudge.jpg');
        $this->verifyApiJsonResponse('POST', $url . '/photo.jpg', 204, 'admin', null, ['photo' => $photo]);

        // Verify we do have a photo now
        $object = $this->verifyApiJsonResponse('GET', $url, 200, 'admin');
        $logoConfig = [
            [
                'href'   => "contests/2/teams/$id/photo.jpg",
                'mime'   => 'image/jpeg',
                'width'  => 320,
                'height' => 200
            ]

        ];
        self::assertSame($logoConfig, $object['photo']);

        // Delete the logo again
        $this->verifyApiJsonResponse('DELETE', $url . '/photo.jpg', 204, 'admin');

        // Verify we have no banner anymore
        $object = $this->verifyApiJsonResponse('GET', $url, 200, 'admin');
        self::assertArrayNotHasKey('photo', $object);
    }
}
