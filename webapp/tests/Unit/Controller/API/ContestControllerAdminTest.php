<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\DemoAboutToStartContestFixture;
use App\DataFixtures\Test\DemoPostUnfreezeContestFixture;
use App\DataFixtures\Test\DemoPreEndContestFixture;
use App\DataFixtures\Test\DemoPreStartContestFixture;
use App\Entity\Contest;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Utils\Utils;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Yaml\Yaml;

class ContestControllerAdminTest extends ContestControllerTest
{
    protected ?string $apiUser = 'admin';

    private function parseSortYaml(string $yamlString): array
    {
        $new = Yaml::parse($yamlString);
        ksort($new);
        return $new;
    }

    public function testAddYaml(): void
    {
        $yaml = <<<EOF
duration: 2:00:00
formal_name: NWERC 2020 Practice Session
penalty_time: 20
scoreboard_freeze_duration: 30:00
id: practice
start_time: 2021-03-27 09:00:00+00:00
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
        $expectedYaml = <<<EOF
id: practice
formal_name: NWERC 2020 Practice Session
name: practice
activate_time: '2021-03-27T09:00:00+00:00'
start_time: '2021-03-27T09:00:00+00:00'
end_time: '2021-03-27T11:00:00+00:00'
duration: 2:00:00.000
penalty_time: 20
medals:
    gold: 4
    silver: 4
    bronze: 4
scoreboard_freeze_time: '2021-03-27T10:30:00+00:00'
scoreboard_freeze_duration: 0:30:00
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
        $url = $this->helperGetEndpointURL('contest-yaml', null, $cid);
        $exportContestYaml = $this->verifyApiResponse('GET', $url, 200, $this->apiUser, null, [], true);
        self::assertIsString($exportContestYaml);
        $expected = $this->parseSortYaml($expectedYaml);
        $actual = $this->parseSortYaml($exportContestYaml);
        self::assertSame($expected, $actual);
        self::assertSame($this->getContest($cid)->getActivatetime(), $this->getContest($cid)->getStarttime());
        self::assertNull($this->getContest($cid)->getDeactivatetime());
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

    protected function getContest(int|string $cid): Contest
    {
        // First clear the entity manager to have all data.
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        return static::getContainer()->get(EntityManagerInterface::class)->getRepository(Contest::class)->findOneBy(['externalid' => $cid]);
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
                'filename' => 'banner.svg',
                'width'    => 510,
                'height'   => 1122,
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

    public function testProblemsetManagement(): void
    {
        // First, make sure we have no problemset document
        $id = 1;
        if ($this->objectClassForExternalId !== null) {
            $id = $this->resolveEntityId($this->objectClassForExternalId, (string)$id);
        }
        $url = $this->helperGetEndpointURL($this->apiEndpoint, (string)$id);
        $object = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);
        self::assertArrayNotHasKey('problemset', $object);

        // Now upload a problemset
        $problemsetFile = __DIR__ . '/../../../../public/doc/logos/DOMjudgelogo.pdf';
        $problemset = new UploadedFile($problemsetFile, 'DOMjudgelogo.pdf');
        $this->verifyApiJsonResponse('POST', $url . '/problemset', 204, $this->apiUser, null, ['problemset' => $problemset]);

        // Verify we do have a problemset now
        $object = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);
        $problemsetConfig = [
            [
                'href'     => "contests/$id/problemset",
                'mime'     => 'application/pdf',
                'filename' => 'problemset.pdf',
            ],
        ];
        self::assertSame($problemsetConfig, $object['problemset']);

        $this->client->request('GET', '/api' . $url . '/problemset');
        /** @var StreamedResponse $response */
        $response = $this->client->getResponse();
        ob_start();
        $response->getCallback()();
        $callbackData = ob_get_clean();
        self::assertEquals(file_get_contents($problemsetFile), $callbackData);

        // Upload the problemset again, this time using PUT to also test that
        $problemsetFile = __DIR__ . '/../../../../public/doc/logos/DOMjudgelogo.pdf';
        $problemset = new UploadedFile($problemsetFile, 'DOMjudgelogo.pdf');
        $this->verifyApiJsonResponse('PUT', $url . '/problemset', 204, $this->apiUser, null, ['problemset' => $problemset]);

        // Verify we still have a problemset
        $object = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);
        self::assertSame($problemsetConfig, $object['problemset']);

        $this->client->request('GET', '/api' . $url . '/problemset');
        /** @var StreamedResponse $response */
        $response = $this->client->getResponse();
        ob_start();
        $response->getCallback()();
        $callbackData = ob_get_clean();
        self::assertEquals(file_get_contents($problemsetFile), $callbackData);

        // Delete the problemset again
        $this->verifyApiJsonResponse('DELETE', $url . '/problemset', 204, $this->apiUser);

        // Verify we have no problemset anymore
        $object = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);
        self::assertArrayNotHasKey('problemset', $object);
    }

    /**
     * @dataProvider provideChangeTimes
     */
    public function testChangeTimes(
        array $body,
        int $expectedResponseCode,
        ?string $expectedBodyContains = null,
        array $extraFixtures = [],
        bool $checkUnfreezeTime = false,
        bool $convertRelativeTimes = false,
    ): void {
        $this->loadFixture(DemoPreStartContestFixture::class);
        $this->loadFixtures($extraFixtures);
        $id = 1;
        if ($this->objectClassForExternalId !== null) {
            $id = $this->resolveEntityId($this->objectClassForExternalId, (string)$id);
        }
        $url = $this->helperGetEndpointURL($this->apiEndpoint, (string)$id);
        if (($body['id'] ?? null) === 1) {
            $body['id'] = $id;
        }

        if ($convertRelativeTimes) {
            if (isset($body['start_time'])) {
                $body['start_time'] = date('Y-m-d\TH:i:s', strtotime($body['start_time']));
            }
            if (isset($body['scoreboard_thaw_time'])) {
                $body['scoreboard_thaw_time'] = date('Y-m-d\TH:i:s', strtotime($body['scoreboard_thaw_time']));
            }
        }

        $content = $this->verifyApiResponse('PATCH', $url, $expectedResponseCode, $this->apiUser, $body);

        if ($expectedBodyContains !== null) {
            self::assertStringContainsString($expectedBodyContains, $content);
        }
        if ($checkUnfreezeTime) {
            $currentTime = (int)Utils::now();
            $contest = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            $contestUnfreezeTime = (new DateTimeImmutable($contest['scoreboard_thaw_time']))->format('U');
            // Allow the unfreeze to happen 1 within the last second, never in the future
            self::assertTrue($contestUnfreezeTime >= $currentTime - 1 && $contestUnfreezeTime <= $currentTime, 'contest did not unfreeze within expected range');
        }
    }

    public function provideChangeTimes(): Generator
    {
        // Note that if the first item contains "id", we replace it with the correct ID in the test

        // General tests
        yield [[], 400, ''];
        yield [['dummy' => 'dummy'], 400, "This value should be of type string."];
        yield [['id' => 1], 400, 'Missing \"start_time\" or \"scoreboard_thaw_time\" in request.'];
        yield [['id' => 1, 'start_time' => null, 'scoreboard_thaw_time' => date('Y-m-d\TH:i:s', strtotime('+15 seconds'))], 400, 'Setting both \"start_time\" and \"scoreboard_thaw_time\" at the same time is not allowed.'];

        // Tests for changing the start time
        yield [['id' => 2, 'start_time' => null], 400, 'Invalid \"id\" in request.'];
        yield [['id' => 1, 'start_time' => null], 403, 'Current contest already started or about to start.', [DemoPreEndContestFixture::class]];
        yield [['id' => 1, 'start_time' => null], 403, 'Current contest already started or about to start.', [DemoAboutToStartContestFixture::class]];
        yield [['id' => 1, 'start_time' => '+15 seconds'], 403, 'New start_time not far enough in the future.', [], false, false];
        yield [['id' => 1, 'start_time' => '+15 seconds', 'force' => false], 403, 'New start_time not far enough in the future.', [], false, false];
        yield [['id' => 1, 'start_time' => '+15 seconds', 'force' => true], 204, null, [], false, false];
        yield [['id' => 1, 'start_time' => 'some invalid start time'], 400, 'Invalid \"start_time\" in request.'];
        yield [['id' => 1, 'start_time' => null, 'force' => true], 204, null, [DemoAboutToStartContestFixture::class]];
        yield [['id' => 1, 'start_time' => null], 204];

        // Tests for changing the unfreeze time
        yield [['id' => 4242, 'scoreboard_thaw_time' => date('Y-m-d\TH:i:s', strtotime('+15 seconds'))], 400, 'Invalid \"id\" in request.'];
        yield [['id' => 1, 'scoreboard_thaw_time' => null], 400, 'Invalid \"scoreboard_thaw_time\" in request.'];
        yield [['id' => 1, 'scoreboard_thaw_time' => 'some invalid start time'], 400, 'Invalid \"scoreboard_thaw_time\" in request.'];
        yield [['id' => 1, 'scoreboard_thaw_time' => '+15 seconds'], 403, 'Current contest already has an unfreeze time set.', [DemoPostUnfreezeContestFixture::class], false, true];
        yield [['id' => 1, 'scoreboard_thaw_time' => '+15 seconds', 'force' => false], 403, 'Current contest already has an unfreeze time set.', [DemoPostUnfreezeContestFixture::class], false, true];
        yield [['id' => 1, 'scoreboard_thaw_time' => '-60 seconds', 'force' => false], 403, 'New scoreboard_thaw_time too far in the past.', [], false, true];
        yield [['id' => 1, 'scoreboard_thaw_time' => '+15 seconds', 'force' => true], 204, null, [DemoPostUnfreezeContestFixture::class], false, true];
        yield [['id' => 1, 'scoreboard_thaw_time' => '+15 seconds'], 204, null, [], false, true];
        yield [['id' => 1, 'scoreboard_thaw_time' => '-15 seconds'], 200, 'Demo contest', [], true, true];
    }

    /**
     * @dataProvider provideNewContest
     */
    public function testActivateTimeContestYaml(
        string $activateTime, string $startTime, ?string $deactivateTime,
        bool $setActivate, bool $setDeactivate
    ): void {
        $yaml = <<<EOF
duration: 2:00:00
name: New Contest to check Activation
penalty-time: 20
scoreboard_freeze_duration: 30:00
id: activation
start_time: {$startTime}
problems:
-   color: '#FE9DAF'
    letter: A
    rgb: '#FE9DAF'
    id: anothereruption
EOF;

        if ($setActivate) {
            $yaml = "activate_time: ".$activateTime."\n".$yaml;
        }
        if ($setDeactivate) {
            $yaml = "deactivate_time: ".$deactivateTime."\n".$yaml;
        }
        $url = $this->helperGetEndpointURL($this->apiEndpoint);
        $tempYamlFile = tempnam(sys_get_temp_dir(), "/contest-yaml-");
        file_put_contents($tempYamlFile, $yaml);
        $yamlFile = new UploadedFile($tempYamlFile, 'contest.yaml');
        $cid = $this->verifyApiJsonResponse('POST', $url, 200, $this->apiUser, [], ['yaml' => $yamlFile]);
        self::assertIsString($cid);
        unlink($tempYamlFile);

        $now = Utils::now();
        $nowTime = Utils::printtime($now, 'Y-m-d H:i:s');
        $activate = Utils::toEpochFloat($activateTime);
        $start = Utils::toEpochFloat($startTime);

        self::assertIsString($cid);
        self::assertSame('New Contest to check Activation', $this->getContest($cid)->getName());
        self::assertSame($start, $this->getContest($cid)->getStarttime());

        if ($setActivate) {
            self::assertSame($activateTime, Utils::printtime($this->getContest($cid)->getActivatetime(), 'Y-m-d H:i:s'));
            self::assertSame($activate, $this->getContest($cid)->getActivatetime());
        } else {
            // Contest uploaded starts in the past
            if (Utils::printtime(Utils::now(), 'Y-m-d H:i:s')>=$startTime) {
                self::assertSame($this->getContest($cid)->getActivatetime(), $this->getContest($cid)->getStarttime());
            } else {
                self::assertTrue($this->getContest($cid)->getActivatetime() <= $now);
            }
        }
        if ($deactivateTime) {
            self::assertSame($deactivateTime, Utils::printtime($this->getContest($cid)->getDeactivatetime(), 'Y-m-d H:i:s'));
        } else {
            self::assertNull($this->getContest($cid)->getDeactivatetime());
        }
    }

    public function provideNewContest(): Generator
    {
        // Test Activation in past, present & future
        yield [Utils::printtime(Utils::now()-14*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()-7*24*60*60, 'Y-m-d H:i:s'), null, true, false];
        yield [Utils::printtime(Utils::now()-7*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now(), 'Y-m-d H:i:s'), null, true, false];
        yield [Utils::printtime(Utils::now()-1, 'Y-m-d H:i:s'), Utils::printtime(Utils::now(), 'Y-m-d H:i:s'), null, true, false];
        yield [Utils::printtime(Utils::now(), 'Y-m-d H:i:s'), Utils::printtime(Utils::now(), 'Y-m-d H:i:s'), null, true, false];
        yield [Utils::printtime(Utils::now(), 'Y-m-d H:i:s'), Utils::printtime(Utils::now()+1, 'Y-m-d H:i:s'), null, true, false];
        yield [Utils::printtime(Utils::now(), 'Y-m-d H:i:s'), Utils::printtime(Utils::now()+7*24*60*60, 'Y-m-d H:i:s'), null, true, false];
        yield [Utils::printtime(Utils::now()+1, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()+7*24*60*60, 'Y-m-d H:i:s'), null, true, false];
        yield [Utils::printtime(Utils::now()+7*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()+14*24*60*60, 'Y-m-d H:i:s'), null, true, false];
        yield [Utils::printtime(Utils::now()-7*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()+7*24*60*60, 'Y-m-d H:i:s'), null, true, false];
        // Test Deactivation in past, present & future
        yield [Utils::printtime(Utils::now()-14*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()-14*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()-7*24*60*60, 'Y-m-d H:i:s'), false, true];
        yield [Utils::printtime(Utils::now()-7*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()-7*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()-1, 'Y-m-d H:i:s'), false, true];
        yield [Utils::printtime(Utils::now()-7*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()-7*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now(), 'Y-m-d H:i:s'), false, true];
        yield [Utils::printtime(Utils::now()-7*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()-7*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()+1, 'Y-m-d H:i:s'), false, true];
        yield [Utils::printtime(Utils::now()-7*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()-7*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()+7*24*60*60, 'Y-m-d H:i:s'), false, true];
        yield [Utils::printtime(Utils::now(), 'Y-m-d H:i:s'), Utils::printtime(Utils::now(), 'Y-m-d H:i:s'), Utils::printtime(Utils::now()+7*24*60*60, 'Y-m-d H:i:s'), false, true];
        yield [Utils::printtime(Utils::now()+1, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()+1, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()+7*24*60*60, 'Y-m-d H:i:s'), false, true];
        yield [Utils::printtime(Utils::now()+7*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()+7*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()+14*24*60*60, 'Y-m-d H:i:s'), false, true];
        // Only activate during the contest
        foreach ([true, false] as $explicitSet) {
            yield [Utils::printtime(Utils::now()-7*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()-7*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()-(7*24-2)*60*60, 'Y-m-d H:i:s'), $explicitSet, true];
            yield [Utils::printtime(Utils::now(), 'Y-m-d H:i:s'), Utils::printtime(Utils::now(), 'Y-m-d H:i:s'), Utils::printtime(Utils::now()+2*60*60, 'Y-m-d H:i:s'), $explicitSet, true];
            yield [Utils::printtime(Utils::now()+7*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()+7*24*60*60, 'Y-m-d H:i:s'), Utils::printtime(Utils::now()+(7*24+2)*60*60, 'Y-m-d H:i:s'), $explicitSet, true];
        }
        // Pick hardcoded times
        yield ["2000-01-01 10:10:10", "2000-01-01 10:10:10", "2002-01-01 10:10:10", false, true];
        yield ["2000-01-01 10:10:10", "2001-01-01 10:10:10", null, true, false];
        yield ["2077-01-01 10:10:10", "2099-01-01 10:10:10", null, true, false];
        yield ["2077-01-01 10:10:10", "2099-01-01 10:10:10", "2100-01-01 10:10:10", true, true];
        yield ["2000-01-01 10:10:10", "2000-01-01 10:10:10", null, false, false];
        yield [Utils::printtime(Utils::now(), 'Y-m-d H:i:s'), "2099-01-01 10:10:10", null, false, false];
        yield [Utils::printtime(Utils::now(), 'Y-m-d H:i:s'), "2077-01-01 10:10:10", "2099-01-01 10:10:10", false, true];
    }
}
