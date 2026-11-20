<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Team;

use PHPUnit\Framework\Attributes\DataProvider;
use App\DataFixtures\Test\DemoAboutToStartContestFixture;
use App\DataFixtures\Test\SampleSubmissionsInBucketsFixture;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Judging;
use App\Entity\Problem;
use App\Entity\Testcase;
use App\Service\ScoreboardService;
use App\Tests\Unit\BaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Generator;

class ProblemControllerTest extends BaseTestCase
{
    protected array $roles = ['team'];

    /**
     * Test that the problem index page shows the correct information.
     */
    #[DataProvider('withLimitsProvider')]
    public function testIndex(bool $withLimits): void
    {
        $problems = [
            'hello',
            'fltcmp',
            'boolfind',
            'jumble',
            'hangman',
        ];
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $problemStatementsData = $em->createQueryBuilder()
            ->from(Problem::class, 'p')
            ->leftJoin('p.problemStatementContent', 'c')
            ->select('p.externalid, c.content')
            ->getQuery()
            ->getResult();
        $problemStatements = [];
        foreach ($problemStatementsData as $data) {
            $problemStatements[array_search($data['externalid'], $problems)] = $data['content'];
        }

        $this->withChangedConfiguration('show_limits_on_team_page', $withLimits,
            function () use (
                $problemStatements,
                $withLimits,
            ): void {
                $descriptions = [
                    'Hello World',
                    'Float special compare test',
                    'Boolean switch search',
                    'Jumble words',
                    'Hangman',
                ];
                $letters = [
                    'A',
                    'B',
                    'C',
                    'D',
                    'E',
                ];
                $crawler = $this->client->request('GET', '/team/problems');

                // Check that the correct menu item is selected.
                static::assertSelectorTextContains('.nav-item .nav-link.active',
                    'Problemset');

                // Get the problem rows and verify we have exactly five of them.
                $rows = $crawler->filter('table.problem-list tbody tr');
                static::assertSame(5, $rows->count());

                for ($i = 0; $i < 5; $i++) {
                    $timelimit = $i === 4 ? '1s' : '5s';
                    $row = $rows->eq($i);
                    static::assertSame($letters[$i],
                        $row->filter('.problem-badge')->text(null, true));
                    static::assertSame($descriptions[$i],
                        $row->filter('.problem-name')->text(null, true));

                    if ($withLimits) {
                        static::assertSame(
                            $timelimit . ' / 2 GB',
                            $row->filter('.problem-limits')->text(null, true)
                        );
                    } else {
                        static::assertSame(0,
                            $row->filter('.problem-limits')->count());
                    }

                    // Download the problem text and make sure it is correct.
                    $problemStatementLink = $row->selectLink('statement');
                    $this->client->click($problemStatementLink->link());

                    static::assertSame($problemStatements[$i], $this->client->getInternalResponse()->getContent());
                }
            });
    }

    public static function withLimitsProvider(): Generator
    {
        yield [false];
        yield [true];
    }

    /**
     * Test that the problem list shows the team's own status per problem, like on
     * the team's own scoreboard row, independent of the public statistics.
     */
    #[DataProvider('problemStatusProvider')]
    public function testProblemStatus(
        bool $verificationRequired,
        bool $showPublicStats,
        bool $invalidateCorrectSubmissions,
        string $expectedStatus
    ): void {
        // The fixture adds correct and incorrect submissions of our own team to the
        // first problem, also during the freeze. It does not update the scoreboard
        // cache, so we refresh it after changing the configuration.
        $this->loadFixture(SampleSubmissionsInBucketsFixture::class);
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        if ($invalidateCorrectSubmissions) {
            foreach ($em->getRepository(Judging::class)->findBy(['result' => Judging::RESULT_CORRECT]) as $judging) {
                $judging->getSubmission()->setValid(false);
            }
            $em->flush();
        }

        $this->withChangedConfiguration('show_public_stats', $showPublicStats,
            fn() => $this->withChangedConfiguration('verification_required', $verificationRequired,
                function () use ($contest, $showPublicStats, $expectedStatus): void {
                    self::getContainer()->get(ScoreboardService::class)->refreshCache($contest);

                    $crawler = $this->client->request('GET', '/team/problems');
                    self::assertResponseIsSuccessful();

                    $rows = $crawler->filter('table.problem-list tbody tr');
                    self::assertCount(1, $rows->eq(0)->filter('.problem-status-' . $expectedStatus));
                    // We did not submit to any of the other problems.
                    self::assertCount($rows->count() - 1, $crawler->filter('.problem-status-none'));
                    self::assertCount(0, $rows->eq(0)->filter('.problem-status-none'));
                    self::assertSame($showPublicStats, $crawler->filter('.problem-stats')->count() > 0);
                }));
    }

    public static function problemStatusProvider(): Generator
    {
        yield 'verdicts visible' => [false, true, false, 'solved'];
        yield 'verdicts not verified yet' => [true, true, false, 'pending'];
        yield 'only incorrect submissions' => [false, true, true, 'attempted'];
        yield 'without public statistics' => [false, false, false, 'solved'];
    }

    /**
     * Test that the problems page shows only sample data.
     */
    public function testSamples(): void
    {
        // First, enable two samples for the fltcmp problem.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var Problem $problem */
        $problem = $em->getRepository(Problem::class)->findOneBy(['externalid' => 'fltcmp']);

        /** @var Testcase[] $samples */
        $samples = [
            1 => $problem->getTestcases()->get(0),
            2 => $problem->getTestcases()->get(2),
        ];
        $samples[1]->setSample(true);
        $samples[2]->setSample(true);
        $em->flush();

        $this->logIn();

        $crawler = $this->client->request('GET', '/team/problems');

        // Get the problem rows.
        $rows = $crawler->filter('table.problem-list tbody tr');

        // Enabling samples does not add attachments to the problems.
        self::assertCount(0, $rows->eq(0)->filter('.btn-outline-secondary'));
        self::assertCount(0, $rows->eq(1)->filter('.btn-outline-secondary'));

        // Check the link to download all samples.
        $link = $rows->eq(1)->selectLink('samples');
        self::assertCount(1, $link);
        self::assertSame(sprintf('/team/%s/samples.zip',
            $problem->getExternalid()),
            $link->attr('href'));

        // Download the sample and make sure the contents are correct.
        $this->client->click($link->link());
        $zipfile = $this->client->getInternalResponse()->getContent();
        $content = $this->unzipString($zipfile);

        for ($i = 1; $i <= 2; $i++) {
            self::assertSame($samples[$i]->getContent()->getInput(), $content["$i.in"]);
            self::assertSame($samples[$i]->getContent()->getOutput(), $content["$i.ans"]);
        }
        // Does not contain more than these 4 files.
        self::assertCount(4, $content);
    }

    /**
     * Test that the problems page does not show sample data for interactive problems.
     */
    public function testInteractiveSamples(): void
    {
        // First, enable a sample for the interactive boolfind problem.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var Problem $problem */
        $problem = $em->getRepository(Problem::class)->findOneBy(['externalid' => 'boolfind']);

        /** @var Testcase $sample */
        $sample = $problem->getTestcases()->get(0);
        $sample->setSample(true);
        $em->flush();

        $this->logIn();

        $crawler = $this->client->request('GET', '/team/problems');

        // Get the problem rows.
        $rows = $crawler->filter('table.problem-list tbody tr');

        // There should be no link to download the samples.
        self::assertCount(0, $rows->eq(2)->selectLink('samples'));

        // Download the sample and make sure the contents are correct.
        $this->client->request('GET', '/team/' . $problem->getProbid() . '/samples.zip');
        $response = $this->client->getResponse();
        self::assertEquals(404, $response->getStatusCode());
    }

    /**
     * Test that problems teams cannot submit to are not accessible by team and public routes.
     */
    public function testProblemNotAllowedForSubmission(): void
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var Problem $problem */
        $problem = $em->getRepository(Problem::class)->findOneBy(['externalid' => 'hello']);
        $problem->getTestcases()->get(0)->setSample(true);
        $em->flush();

        $this->logIn();

        $endpoints = [
            '/team/problems/hello/statement',
            '/team/hello/samples.zip',
            '/public/problems/hello/statement',
            '/public/hello/samples.zip',
            '/public/clarifications/by-problem/hello',
        ];
        foreach ($endpoints as $endpoint) {
            $this->client->request('GET', $endpoint);
            $statusCode = $this->client->getResponse()->getStatusCode();
            static::assertSame(200, $statusCode, "Expected status code 200, got {$statusCode} for {$endpoint}");
        }

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->getRepository(Problem::class)->findOneBy(['externalid' => 'hello'])
            ->getContestProblems()->first()->setAllowSubmit(false);
        $em->flush();

        foreach ($endpoints as $endpoint) {
            $this->client->request('GET', $endpoint);
            $statusCode = $this->client->getResponse()->getStatusCode();
            static::assertSame(404, $statusCode, "Expected status code 404, got {$statusCode} for {$endpoint}");
        }
    }

    /**
     * Test that the problems page does not show page, statement, sample data before contest start.
     */
    public function testAccessProblemBeforeContestStarts(): void
    {
        $this->loadFixtures([DemoAboutToStartContestFixture::class]);

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $problems = $em->createQueryBuilder()
            ->select('p.probid')
            ->from(ContestProblem::class, 'cp')
            ->join('cp.problem', 'p')
            ->join('cp.contest', 'c')
            ->where('c.shortname = :shortname')
            ->setParameter('shortname', 'demo')
            ->getQuery()
            ->getResult();
        $probids = array_column($problems, 'probid');

        $this->client->request('GET', '/public/problems');
        static::assertSelectorTextContains('.nav-item .nav-link.disabled', 'Problems');
        static::assertSelectorTextContains('.alert.alert-secondary', 'No problem texts available at this point.');

        foreach ($probids as $id) {
            $endpoints = [
                "/team/problems/{$id}",
                "/team/problems/{$id}/statement",
                "/team/problems/{$id}/samples.zip"
            ];

            foreach ($endpoints as $endpoint) {
                $this->client->request('GET', $endpoint);
                $statusCode = $this->client->getResponse()->getStatusCode();
                static::assertSame(404, $statusCode, "Expected status code 404, got {$statusCode} for {$endpoint}");
            }
        }
    }
}
