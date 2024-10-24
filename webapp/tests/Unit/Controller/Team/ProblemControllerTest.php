<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Team;

use App\DataFixtures\Test\DemoAboutToStartContestFixture;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Problem;
use App\Entity\Testcase;
use App\Tests\Unit\BaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Generator;

class ProblemControllerTest extends BaseTestCase
{
    protected array $roles = ['team'];

    /**
     * Test that the problem index page shows the correct information.
     *
     * @dataProvider withLimitsProvider
     */
    public function testIndex(bool $withLimits): void
    {
        $problems = [
            'hello',
            'fltcmp',
            'boolfind',
        ];
        $descriptions = [
            'Hello World',
            'Float special compare test',
            'Boolean switch search',
        ];
        $letters = [
            'A',
            'B',
            'C',
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
                $descriptions,
                $withLimits,
                $letters
            ) {
                $crawler = $this->client->request('GET', '/team/problems');

                // Check that the correct menu item is selected.
                static::assertSelectorTextContains('.nav-item .nav-link.active',
                    'Problemset');

                // Get the card bodies and verify we have exactly three of them.
                $cardBodies = $crawler->filter('.card-body');
                static::assertSame(3, $cardBodies->count());

                for ($i = 0; $i < 3; $i++) {
                    $card = $cardBodies->eq($i);
                    static::assertSame($letters[$i],
                        $card->filter('.card-title')->text(null, true));
                    static::assertSame($descriptions[$i],
                        $card->filter('h3.card-subtitle')->text(null, true));

                    if ($withLimits) {
                        static::assertSame(
                            'Limits: 5 seconds / 2 GB',
                            $card->filter('h4.card-subtitle')->text(null, true)
                        );
                    } else {
                        static::assertSame(0,
                            $card->filter('h4.card-subtitle')->count());
                    }

                    // Download the problem text and make sure it is correct.
                    $problemStatementLink = $card->selectLink('statement');
                    $this->client->click($problemStatementLink->link());

                    static::assertSame($problemStatements[$i], $this->client->getInternalResponse()->getContent());
                }
            });
    }

    public function withLimitsProvider(): Generator
    {
        yield [false];
        yield [true];
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

        // Get the card bodies.
        $cardBodies = $crawler->filter('.card-body');

        // The first and last card should not have any samples.
        self::assertSame(0,
            $cardBodies->eq(0)->filter('.list-group .list-group-item')->count());
        self::assertSame(0,
            $cardBodies->eq(2)->filter('.list-group .list-group-item')->count());

        // Check the link to download all samples.
        $link = $cardBodies->eq(1)->filter('a')->eq(1);
        self::assertSame('samples', $link->text(null, true));
        self::assertSame(sprintf('/team/%d/samples.zip',
            $problem->getProbid()),
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

        // Get the card bodies.
        $cardBodies = $crawler->filter('.card-body');

        // The last card should not have any samples.
        self::assertSame(0,
            $cardBodies->eq(2)->filter('.list-group .list-group-item')->count());

        // Check the link to download all samples.
        $link = $cardBodies->eq(2)->filter('a')->eq(1);
        self::assertNotSame('samples', $link->text(null, true));

        // Download the sample and make sure the contents are correct.
        $this->client->request('GET', '/team/' . $problem->getProbid() . '/samples.zip');
        $response = $this->client->getResponse();
        self::assertEquals(404, $response->getStatusCode());
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
