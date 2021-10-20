<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\Entity\Contest;
use App\Entity\Problem;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProblemControllerAdminTest extends ProblemControllerTest
{
    protected $apiUser = 'admin';

    protected function setUp(): void
    {
        // When queried as admin, extra information is returned about each problem
        $this->expectedObjects[1]['test_data_count'] = 1;
        $this->expectedObjects[2]['test_data_count'] = 3;
        $this->expectedObjects[3]['test_data_count'] = 1;
        parent::setUp();
    }

    public function testAddJson()
    {
        $json = <<<EOF
[
    {
        "color": "greenyellow",
        "externalid": "ascendingphoto",
        "id": "ascendingphoto",
        "label": "A",
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
        "label": "B",
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
        "label": "C",
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

        $expectedProblems = ['A' => 'ascendingphoto', 'B' => 'boss', 'C' => 'connect'];

        // First clear the entity manager to have all data
        static::$container->get(EntityManagerInterface::class)->clear();

        $addedProblems = [];

        // Now load the problems with the given ID's
        $config = static::$container->get(ConfigurationService::class);
        $dataSource = $config->get('data_source');
        foreach ($ids as $id) {
            if ($dataSource === DOMJudgeService::DATA_SOURCE_LOCAL) {
                /** @var Problem $problem */
                $problem = static::$container->get(EntityManagerInterface::class)->getRepository(Problem::class)->find($id);
            } else {
                $problem = static::$container->get(EntityManagerInterface::class)->getRepository(Problem::class)->findOneBy(['externalid' => $id]);
            }

            $addedProblems[$problem->getContestProblems()->first()->getShortName()] = $problem->getExternalid();
        }

        self::assertEquals($expectedProblems, $addedProblems);
    }
}
