<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

class OrganizationControllerTest extends BaseTest
{
    protected $apiEndpoint = 'organizations';

    protected $expectedObjects = [
        '1' => [
            'icpc_id'     => '1',
            'shortname'   => 'UU',
            'id'          => '1',
            'name'        => 'UU',
            'formal_name' => 'Utrecht University',
            'country'     => 'NLD',
            'logo'           => [
                [
                    'href'   => 'contests/2/organizations/1/logo.png',
                    'mime'   => 'image/png',
                    'width'  => 181,
                    'height' => 101
                ]
            ]
        ],
    ];

    protected $expectedAbsent = ['4242', 'nonexistent'];

    /**
     * @var string
     */
    protected $organizationLogo;

    protected function setUp(): void
    {
        // Make sure we have an organization logo for organization 1 by copying an existing file
        $fileToCopy = __DIR__ . '/../../../../public/js/hv.png';
        $organizationLogosDir = __DIR__ . '/../../../../public/images/affiliations/';
        $this->organizationLogo = $organizationLogosDir . '1.png';
        copy($fileToCopy, $this->organizationLogo);

        // Make sure we remove the test container, since we need to rebuild it for the images to work
        $this->removeTestContainer();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Remove the image again
        unlink($this->organizationLogo);
        $this->removeTestContainer();
    }
}
