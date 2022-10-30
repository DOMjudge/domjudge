<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\DummyProblemFixture;
use App\DataFixtures\Test\LockedContestFixture;
use App\Entity\Problem;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProblemControllerAdminTest extends ProblemControllerTest
{
    protected ?string $apiUser = 'admin';

    protected function setUp(): void
    {
        // When queried as admin, extra information is returned about each problem.
        $this->expectedObjects[1]['test_data_count'] = 1;
        $this->expectedObjects[2]['test_data_count'] = 1+3; // 1 sample, 3 secret cases
        $this->expectedObjects[3]['test_data_count'] = 1;
        parent::setUp();
    }

    public function testAddJson(): void
    {
        $json = <<<EOF
[
    {
        "color": "greenyellow",
        "externalid": "ascendingphoto",
        "id": "ascendingphoto",
        "label": "D",
        "name": "Ascending Photo",
        "ordinal": 0,
        "rgb": "#aeff21",
        "test_data_count": 26,
        "time_limit": 3.0
    },
    {
        "color": "blueviolet",
        "externalid": "boss",
        "id": "boss",
        "label": "E",
        "name": "Boss Battle",
        "ordinal": 1,
        "rgb": "#5b29ff",
        "test_data_count": 28,
        "time_limit": 2.0
    },
    {
        "color": "hotpink",
        "externalid": "connect",
        "id": "connect",
        "label": "F",
        "name": "Connect the Dots",
        "ordinal": 2,
        "rgb": "#ff4fa7",
        "test_data_count": 34,
        "time_limit": 2.0
    }
]
EOF;

        $url = $this->helperGetEndpointURL($this->apiEndpoint) . '/add-data';
        $tempJsonFile = tempnam(sys_get_temp_dir(), "/problems-json-");
        file_put_contents($tempJsonFile, $json);
        $jsonFile = new UploadedFile($tempJsonFile, 'problems.json');
        $ids = $this->verifyApiJsonResponse('POST', $url, 200, $this->apiUser, [], ['data' => $jsonFile]);
        unlink($tempJsonFile);

        self::assertIsArray($ids);

        $expectedProblems = ['D' => 'ascendingphoto', 'E' => 'boss', 'F' => 'connect'];

        // First clear the entity manager to have all data.
        static::getContainer()->get(EntityManagerInterface::class)->clear();

        $addedProblems = [];

        // Now load the problems with the given IDs.
        $config = static::getContainer()->get(ConfigurationService::class);
        $dataSource = $config->get('data_source');
        foreach ($ids as $id) {
            if ($dataSource === DOMJudgeService::DATA_SOURCE_LOCAL) {
                /** @var Problem $problem */
                $problem = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Problem::class)->find($id);
            } else {
                $problem = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Problem::class)->findOneBy(['externalid' => $id]);
            }

            $addedProblems[$problem->getContestProblems()->first()->getShortName()] = $problem->getExternalid();
        }

        self::assertEquals($expectedProblems, $addedProblems);
    }

    public function testDelete(): void
    {
        // Check that we can delete the problem
        $url = $this->helperGetEndpointURL($this->apiEndpoint) . '/2';
        $this->verifyApiJsonResponse('DELETE', $url, 204, $this->apiUser);

        // Check that we now have two problems left
        $indexUrl = $this->helperGetEndpointURL($this->apiEndpoint);
        $problems = $this->verifyApiJsonResponse('GET', $indexUrl, 200, $this->apiUser);
        self::assertCount(2, $problems);
    }

    public function testDeleteNotFound(): void
    {
        // Check that we can delete the problem
        $url = $this->helperGetEndpointURL($this->apiEndpoint) . '/4';
        $this->verifyApiJsonResponse('DELETE', $url, 404, $this->apiUser);
    }

    public function testAdd(): void
    {
        $this->loadFixture(DummyProblemFixture::class);

        $body = [
            'label'        => 'newproblem',
            'points'       => 3,
            'rgb'        => '#013370',
            'allow_submit' => true,
            'allow_judge'  => true,
        ];

        $problemId = $this->resolveReference(DummyProblemFixture::class . ':0');

        // Check that we can not add any problem
        $url             = $this->helperGetEndpointURL($this->apiEndpoint) . '/' . $problemId;
        $problemResponse = $this->verifyApiJsonResponse('PUT', $url, 200, $this->apiUser, $body);

        $expected = [
            'id'         => $problemId,
            'ordinal'    => 3, // `newproblem` comes after `boolfind`, `fltcmp` and `hello`
            'time_limit' => 2,
            'name'       => 'Dummy problem',
            'label'      => $body['label'],
            'color'      => 'midnightblue', // Closest to #013370
            'rgb'        => $body['rgb'],
        ];

        foreach ($expected as $key => $value) {
            self::assertArrayHasKey($key, $problemResponse);
            self::assertEquals($value, $problemResponse[$key], "$key has correct value");
        }

        // Check that we now have four problems
        $indexUrl = $this->helperGetEndpointURL($this->apiEndpoint);
        $problems = $this->verifyApiJsonResponse('GET', $indexUrl, 200, $this->apiUser);
        self::assertCount(4, $problems);
    }

    public function testAddNotFound(): void
    {
        // Check that we can delete the problem
        $url = $this->helperGetEndpointURL($this->apiEndpoint) . '/notfound';
        $response = $this->verifyApiJsonResponse('PUT', $url, 404, $this->apiUser, ['label' => 'dummy']);
        self::assertEquals("Object with ID 'notfound' not found", $response['message']);
    }

    public function testAddExisting(): void
    {
        $this->loadFixture(DummyProblemFixture::class);

        // Check that we can not add a problem that is already added
        $url = $this->helperGetEndpointURL($this->apiEndpoint) . '/2';
        $response = $this->verifyApiJsonResponse('PUT', $url, 400, $this->apiUser, ['label' => 'dummy']);
        self::assertEquals('Problem already linked to contest', $response['message']);
    }

    public function testAddToLocked(): void
    {
        $this->loadFixture(LockedContestFixture::class);
        $this->loadFixture(DummyProblemFixture::class);

        $body = [
            'label'        => 'newproblem',
            'points'       => 3,
            'rgb'          => '#013370',
            'allow_submit' => true,
            'allow_judge'  => true,
        ];

        $problemId = $this->resolveReference(DummyProblemFixture::class . ':0');

        $url = $this->helperGetEndpointURL($this->apiEndpoint) . '/' . $problemId;
        $problemResponse = $this->verifyApiJsonResponse('PUT', $url, 403, $this->apiUser, $body);
        self::assertStringContainsString('Contest is locked', $problemResponse['message']);
    }

    public function testDeleteFromLocked(): void
    {
        $this->loadFixture(LockedContestFixture::class);

        // Check that we cannot delete the problem.
        $url = $this->helperGetEndpointURL($this->apiEndpoint) . '/2';
        $problemResponse = $this->verifyApiJsonResponse('DELETE', $url, 403, $this->apiUser);
        self::assertStringContainsString('Contest is locked', $problemResponse['message']);
    }
}
