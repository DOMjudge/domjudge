<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\SampleAffiliationsFixture;
use App\Entity\TeamAffiliation;
use App\Service\ConfigurationService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class OrganizationControllerTest extends BaseTest
{
    protected $apiEndpoint = 'organizations';

    protected $expectedObjects = [
        '1'                                      => [
            'icpc_id'      => '1',
            'shortname'    => 'UU',
            'id'           => '1',
            'name'         => 'UU',
            'formal_name'  => 'Utrecht University',
            'country'      => 'NLD',
            'country_flag' => [
                [
                    'href'   => 'country-flags/NLD/4x3.svg',
                    'mime'   => 'image/svg+xml',
                    'width'  => 640,
                    'height' => 480,
                ],
                [
                    'href'   => 'country-flags/NLD/1x1.svg',
                    'mime'   => 'image/svg+xml',
                    'width'  => 512,
                    'height' => 512,
                ],
            ],
            'logo' => null,
        ],
        SampleAffiliationsFixture::class . ':0' => [
            'name'         => 'FAU',
            'formal_name'  => 'Friedrich-Alexander-Universität Erlangen-Nürnberg',
            'country'      => 'DEU',
            'country_flag' => [
                [
                    'href'   => 'country-flags/DEU/4x3.svg',
                    'mime'   => 'image/svg+xml',
                    'width'  => 640,
                    'height' => 480,
                ],
                [
                    'href'   => 'country-flags/DEU/1x1.svg',
                    'mime'   => 'image/svg+xml',
                    'width'  => 512,
                    'height' => 512,
                ],
            ],
        ],
        SampleAffiliationsFixture::class . ':1' => [
            'name'         => 'ABC',
            'formal_name'  => 'Affiliation without country',
            'country'      => null,
            'country_flag' => null,
        ],
    ];

    protected $objectClassForExternalId = TeamAffiliation::class;

    protected $expectedAbsent = ['4242', 'nonexistent'];

    protected static $fixtures = [SampleAffiliationsFixture::class];

    public function testList()
    {
        // Remove country and country flag if not enabled.
        $showFlags = static::$container->get(ConfigurationService::class)->get('show_flags');
        if (!$showFlags) {
            foreach ($this->expectedObjects as &$object) {
                $object['country'] = null;
                $object['country_flag'] = null;
            }
            unset($object);
        }
        parent::testList();
    }

    /**
     * @dataProvider provideSingle
     */
    public function testSingle($id, array $expectedProperties)
    {
        // Remove country and country flag if not enabled.
        $showFlags = static::$container->get(ConfigurationService::class)->get('show_flags');
        if (!$showFlags) {
            $expectedProperties['country'] = null;
            $expectedProperties['country_flag'] = null;
        }
        parent::testSingle($id, $expectedProperties);
    }

    /**
     * Test that when we disable showing country flags, the country and flag of an affiliation are not exposed.
     */
    public function testCountryAbsentWhenDisabled()
    {
        $this->withChangedConfiguration('show_flags', false, function () {
            $apiEndpoint = $this->apiEndpoint;
            $contestId = $this->getDemoContestId();
            // The hardcoded 1 here is the team affiliation from the TeamAffiliationFixture example data fixture.
            $organizationId = $this->dataSourceIsLocal() ? 1 : 'utrecht';
            $response = $this->verifyApiJsonResponse('GET', "/contests/$contestId/$apiEndpoint/$organizationId", 200);

            static::assertArrayNotHasKey('country', $response);
            static::assertArrayNotHasKey('country_flag', $response);
        });
    }

    public function testLogoManagement(): void
    {
        // Note: we are doing this as admin as we require privileges

        // First, make sure we have no logo
        $id = 1;
        if ($this->objectClassForExternalId !== null) {
            $id = $this->resolveEntityId($this->objectClassForExternalId, (string)$id);
        }
        $url = $this->helperGetEndpointURL($this->apiEndpoint, (string)$id);
        $object = $this->verifyApiJsonResponse('GET', $url, 200, 'admin');
        self::assertArrayNotHasKey('logo', $object);

        // Now upload a logo
        $logo = new UploadedFile(__DIR__ . '/../../../../public/js/hv.png', 'hv.png');
        $this->verifyApiJsonResponse('POST', $url . '/logo.png', 204, 'admin', null, ['logo' => $logo]);

        // Verify we do have a logo now
        $object = $this->verifyApiJsonResponse('GET', $url, 200, 'admin');
        $logoConfig = [
            [
                'href'   => "contests/2/organizations/$id/logo.png",
                'mime'   => 'image/png',
                'width'  => 181,
                'height' => 101
            ]
        ];
        self::assertSame($logoConfig, $object['logo']);

        // Delete the logo again
        $this->verifyApiJsonResponse('DELETE', $url . '/logo.png', 204, 'admin');

        // Verify we have no banner anymore
        $object = $this->verifyApiJsonResponse('GET', $url, 200, 'admin');
        self::assertArrayNotHasKey('logo', $object);
    }
}
