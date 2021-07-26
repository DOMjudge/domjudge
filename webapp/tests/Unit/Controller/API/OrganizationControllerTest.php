<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\SampleAffilicationsFixture;
use App\Entity\TeamAffiliation;
use App\Service\ConfigurationService;

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
            'logo'         => [
                [
                    'href'   => 'contests/2/organizations/1/logo.png',
                    'mime'   => 'image/png',
                    'width'  => 510,
                    'height' => 1123
                ]
            ]
        ],
        SampleAffilicationsFixture::class . ':0' => [
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
        SampleAffilicationsFixture::class . ':1' => [
            'name'         => 'ABC',
            'formal_name'  => 'Affiliation without country',
            'country'      => null,
            'country_flag' => null,
        ],
    ];

    protected $objectClassForExternalId = TeamAffiliation::class;

    protected $expectedAbsent = ['4242', 'nonexistent'];

    protected static $fixtures = [SampleAffilicationsFixture::class];

    public function testList()
    {
        // Remove country and country flag if not enabled
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
        // Remove country and country flag if not enabled
        $showFlags = static::$container->get(ConfigurationService::class)->get('show_flags');
        if (!$showFlags) {
            $expectedProperties['country'] = null;
            $expectedProperties['country_flag'] = null;
        }
        parent::testSingle($id, $expectedProperties);
    }

    /**
     * @var string
     */
    protected $organizationLogo;

    protected function setUp(): void
    {
        // Make sure we have an organization logo for organization 1 by copying an existing file
        $fileToCopy = __DIR__ . '/../../../../public/doc/logos/DOMjudgelogo.png';
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

    /**
     * Test that when we disable showing country flags, the country and flag of an affiliation are not exposed
     */
    public function testCountryAbsentWhenDisabled()
    {
        $this->withChangedConfiguration('show_flags', false, function () {
            $apiEndpoint = $this->apiEndpoint;
            $contestId = $this->getDemoContestId();
            // The hardcoded 1 here is the team affiliation from the TeamAffiliationFixture example data fixture
            $organizationId = $this->dataSourceIsLocal() ? 1 : 'utrecht';
            $response = $this->verifyApiJsonResponse('GET', "/contests/$contestId/$apiEndpoint/$organizationId", 200);

            static::assertArrayNotHasKey('country', $response);
            static::assertArrayNotHasKey('country_flag', $response);
        });
    }
}
