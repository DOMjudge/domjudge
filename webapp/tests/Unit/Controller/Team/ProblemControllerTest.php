<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Team;

use App\Entity\Problem;
use App\Entity\Testcase;
use App\Tests\Unit\BaseTest;
use Doctrine\ORM\EntityManagerInterface;
use Generator;

class ProblemControllerTest extends BaseTest
{
    protected array $roles = ['team'];

    /**
     * Test that the problem index page shows the correct information.
     *
     * @dataProvider withLimitsProvider
     */
    public function testIndex(bool $withLimits): void
    {
        $problems     = [
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
        $em               = self::getContainer()->get(EntityManagerInterface::class);
        $problemTextsData = $em->createQueryBuilder()
            ->from('App:Problem', 'p')
            ->select('p.externalid, p.problemtext')
            ->getQuery()
            ->getResult();
        $problemTexts     = [];
        foreach ($problemTextsData as $data) {
            $problemTexts[array_search($data['externalid'], $problems)] =
                stream_get_contents($data['problemtext']);
        }

        $this->withChangedConfiguration('show_limits_on_team_page', $withLimits,
            function () use (
                $problemTexts,
                $descriptions,
                $problems,
                $withLimits,
                $letters
            ) {
                $crawler = $this->client->request('GET', '/team/problems');

                // Check that the correct menu item is selected.
                $this->assertSelectorTextContains('.nav-item.active .nav-link',
                    'Problemset');

                // Get the card bodies and verify we have exactly three of them.
                $cardBodies = $crawler->filter('.card-body');
                $this->assertSame(3, $cardBodies->count());

                for ($i = 0; $i < 3; $i++) {
                    $card = $cardBodies->eq($i);
                    $this->assertSame($letters[$i],
                        $card->filter('.card-title')->text(null, true));
                    $this->assertSame($descriptions[$i],
                        $card->filter('h3.card-subtitle')->text(null, true));

                    if ($withLimits) {
                        $this->assertSame(
                            'Limits: 5 seconds / 2 GB',
                            $card->filter('h4.card-subtitle')->text(null, true)
                        );
                    } else {
                        $this->assertSame(0,
                            $card->filter('h4.card-subtitle')->count());
                    }

                    // Download the problem text and make sure it is correct.
                    $problemTextLink = $card->selectLink('text');
                    ob_start();
                    $this->client->click($problemTextLink->link());
                    $content = ob_get_clean();

                    $this->assertSame($problemTexts[$i], $content);
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
            2 => $problem->getTestcases()->get(2)
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
        // We use ob_ methods since this is a streamed response.
        ob_start();
        $this->client->click($link->link());
        $zipfile = ob_get_clean();
        $content = $this->unzipString($zipfile);

        for ($i = 1; $i <= 2; $i++) {
            self::assertSame($samples[$i]->getContent()->getInput(), $content["$i.in"]);
            self::assertSame($samples[$i]->getContent()->getOutput(), $content["$i.ans"]);
        }
        // Does not contain more than these 4 files.
        self::assertCount(4, $content);
    }
}
