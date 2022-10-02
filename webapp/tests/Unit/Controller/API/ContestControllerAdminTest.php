<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\Entity\Contest;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ContestControllerAdminTest extends ContestControllerTest
{
    protected ?string $apiUser = 'admin';

    public function testAddYaml(): void
    {
        $yaml = <<<EOF
duration: 2:00:00
name: NWERC 2020 Practice Session
penalty-time: 20
scoreboard-freeze-length: 30:00
short-name: practice
start-time: 2021-03-27 09:00:00+00:00
problems:
-   color: '#FE9DAF'
    letter: A
    rgb: '#FE9DAF'
    short-name: anothereruption
-   color: '#008100'
    letter: B
    rgb: '#008100'
    short-name: brokengears
-   color: '#FF7109'
    letter: C
    rgb: '#FF7109'
    short-name: cheating
EOF;

        $url = $this->helperGetEndpointURL($this->apiEndpoint);
        $tempYamlFile = tempnam(sys_get_temp_dir(), "/contest-yaml-");
        file_put_contents($tempYamlFile, $yaml);
        $yamlFile = new UploadedFile($tempYamlFile, 'contest.yaml');
        $cid = $this->verifyApiJsonResponse('POST', $url, 200, $this->apiUser, [], ['yaml' => $yamlFile]);
        self::assertIsString($cid);
        unlink($tempYamlFile);

        self::assertIsString($cid);
        self::assertSame('NWERC 2020 Practice Session', $this->getContest($cid)->getName());
    }

    public function testAddJson(): void
    {
        $json = <<<EOF
{
    "duration": "5:00:00.000",
    "formal_name": "NWERC 2018 - Testing",
    "id": "nwerc18t",
    "name": "NWERC 2018 - Testing",
    "penalty_time": 20,
    "scoreboard_freeze_duration": "1:00:00.000",
    "start_time": "2018-11-23T17:45:00+01:00"
}
EOF;

        $url = $this->helperGetEndpointURL($this->apiEndpoint);
        $tempJsonFile = tempnam(sys_get_temp_dir(), "/contest-json-");
        file_put_contents($tempJsonFile, $json);
        $jsonFile = new UploadedFile($tempJsonFile, 'contest.json');
        $cid = $this->verifyApiJsonResponse('POST', $url, 200, $this->apiUser, [], ['json' => $jsonFile]);
        unlink($tempJsonFile);

        self::assertIsString($cid);
        self::assertSame('NWERC 2018 - Testing', $this->getContest($cid)->getName());
    }

    protected function getContest($cid): Contest
    {
        // First clear the entity manager to have all data.
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $config = static::getContainer()->get(ConfigurationService::class);
        $dataSource = $config->get('data_source');
        if ($dataSource === DOMJudgeService::DATA_SOURCE_LOCAL) {
            return static::getContainer()->get(EntityManagerInterface::class)->getRepository(Contest::class)->find($cid);
        } else {
            return static::getContainer()->get(EntityManagerInterface::class)->getRepository(Contest::class)->findOneBy(['externalid' => $cid]);
        }
    }

    public function testBannerManagement(): void
    {
        // First, make sure we have no banner
        $id = 1;
        if ($this->objectClassForExternalId !== null) {
            $id = $this->resolveEntityId($this->objectClassForExternalId, (string)$id);
        }
        $url = $this->helperGetEndpointURL($this->apiEndpoint, (string)$id);
        $object = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);
        self::assertArrayNotHasKey('banner', $object);

        // Now upload a banner
        $bannerFile = __DIR__ . '/../../../../public/images/DOMjudgelogo.svg';
        $banner = new UploadedFile($bannerFile, 'DOMjudgelogo.svg');
        $this->verifyApiJsonResponse('POST', $url . '/banner', 204, $this->apiUser, null, ['banner' => $banner]);

        // Verify we do have a banner now
        $object = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);
        $bannerConfig = [
            [
                'href'     => "contests/$id/banner",
                'mime'     => 'image/svg+xml',
                'width'    => 510,
                'height'   => 1122,
                'filename' => 'banner.svg',
            ],
        ];
        self::assertSame($bannerConfig, $object['banner']);

        $this->client->request('GET', '/api' . $url . '/banner');
        /** @var BinaryFileResponse $response */
        $response = $this->client->getResponse();
        self::assertFileEquals($bannerFile, $response->getFile()->getRealPath());

        // Delete the banner again
        $this->verifyApiJsonResponse('DELETE', $url . '/banner', 204, $this->apiUser);

        // Verify we have no banner anymore
        $object = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);
        self::assertArrayNotHasKey('banner', $object);
    }
}
