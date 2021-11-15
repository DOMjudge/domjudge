<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

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
            'photo'           => [
                [
                    'href'   => 'contests/2/teams/2/photo.jpg',
                    'mime'   => 'image/jpeg',
                    'width'  => 320,
                    'height' => 200
                ]
            ]
        ],
    ];

    protected $expectedAbsent = ['4242', 'nonexistent'];

    /**
     * @var string
     */
    protected $teamPhoto;

    protected function setUp(): void
    {
        // Make sure we have a team photo for team 2 by copying an existing file.
        $teamPhotosDir = __DIR__ . '/../../../../public/images/teams/';
        $this->teamPhoto = $teamPhotosDir . '2.jpg';
        copy($teamPhotosDir . 'domjudge.jpg', $this->teamPhoto);

        // Make sure we remove the test container, since we need to rebuild it for the images to work.
        $this->removeTestContainer();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Remove the image again.
        unlink($this->teamPhoto);
        $this->removeTestContainer();
    }
}
