<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\ContestTimeFixture;
use App\DataFixtures\Test\BalloonCorrectSubmissionFixture;
use App\DataFixtures\Test\BalloonUserFixture;
use App\Entity\Contest;
use Doctrine\ORM\EntityManagerInterface;
use Generator;

class BalloonsControllerTest extends BaseTestCase
{
    public function getUnitContestId(): string
    {
        $this->loadFixtures([ContestTimeFixture::class]);
        /** @var EntityManagerInterface $manager */
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var Contest $contest */
        $contest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'beforeFreeze']);
        return $contest->getExternalid();
    }

    /**
     * In the default test setup there are no judgings yet, so no balloons,
     */
    public function testBalloonsNoJudgings(): void
    {
        $this->loadFixture(BalloonUserFixture::class);
        $contestId = $this->getUnitContestId();
        $url = "/contests/$contestId/balloons";
        foreach (['admin','balloonuser'] as $user) {
            $response = $this->verifyApiJsonResponse('GET', $url, 200, $user);
            static::assertEquals([], $response);
        }
    }

    public function testMarkAsDone(): void
    {
        $expectedBalloon = ['team'=>'t2: Example teamname', 'problem'=>'U'];
        $contestId = $this->getUnitContestId();
        $url = "/contests/$contestId/balloons?todo=1";
        $this->loadFixtures([BalloonCorrectSubmissionFixture::class,BalloonUserFixture::class]);
        /** @var array $response */
        $response = $this->verifyApiJsonResponse('GET', $url, 200, 'admin');
        self::assertEquals(count($response), 1);
        foreach ($expectedBalloon as $key => $value) {
            static::assertEquals($response[0][$key], $value);
        }
        $balloonId = $response[0]['balloonid'];
        $postUrl = "/contests/$contestId/balloons/$balloonId/done";
        $this->verifyApiJsonResponse('POST', $postUrl, 204, 'balloonuser');
        $response = $this->verifyApiJsonResponse('GET', $url, 200, 'admin');
        self::assertEquals([], $response);
        $url = "/contests/$contestId/balloons?todo=0";
        /** @var array $response */
        $response = $this->verifyApiJsonResponse('GET', $url, 200, 'balloonuser');
        self::assertEquals(count($response), 1);
        $this->verifyApiJsonResponse('POST', $postUrl, 204, 'balloonuser');
    }

    public function testMarkInvalidBalloonAsDone(): void
    {
        $balloonId = 404999;
        $contestId = $this->getUnitContestId();
        $postUrl = "/contests/$contestId/balloons/$balloonId/done";
        $this->verifyApiJsonResponse('POST', $postUrl, 404, 'admin');
    }

    public function testMarkAsDoneWrongContest(): void
    {
        // BalloonIDs are global so they can be marked done against other contests.
        $expectedBalloon = ['team'=>'t2: Example teamname', 'problem'=>'U'];
        $contestId = $this->getUnitContestId();
        $url = "/contests/$contestId/balloons?todo=1";
        $this->loadFixtures([BalloonCorrectSubmissionFixture::class,BalloonUserFixture::class]);
        /** @var array $response */
        $response = $this->verifyApiJsonResponse('GET', $url, 200, 'admin');
        $balloonId = $response[0]['balloonid'];
        $postUrl = "/contests/424242/balloons/$balloonId/done";
        $this->verifyApiJsonResponse('POST', $postUrl, 204, 'balloonuser');
        $response = $this->verifyApiJsonResponse('GET', $url, 200, 'admin');
        self::assertEquals(0, count($response));
    }

    /**
     * @dataProvider provideUnprivilegedUsers
     */
    public function testBalloonsAccessForPrivilegedUsersOnly(?string $user, int $result): void
    {
        $contestId = $this->getUnitContestId();
        $this->verifyApiJsonResponse('GET', "/contests/$contestId/balloons", $result, $user);
    }

    public function provideUnprivilegedUsers(): Generator
    {
        yield [null, 401];
        yield ['demo', 403];
    }
}
