<?php declare(strict_types=1);

namespace App\Tests\Controller\Team;

use App\Entity\Problem;
use App\Entity\Testcase;
use App\Tests\BaseTest;
use Doctrine\ORM\EntityManagerInterface;

class ProblemControllerTest extends BaseTest
{
    protected static $roles = ['team'];

    /**
     * Test that the problem index page shows the correct information
     *
     * @dataProvider withLimitsProvider
     *
     * @param bool $withLimits
     */
    public function testIndex(bool $withLimits)
    {
        $problems     = [
            'boolfind',
            'fltcmp',
            'hello',
        ];
        $descriptions = [
            'Boolean switch search',
            'Float special compare test',
            'Hello World',
        ];
        /** @var EntityManagerInterface $em */
        $em               = self::$container->get(EntityManagerInterface::class);
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
                $withLimits
            ) {
                $this->logIn();

                $crawler = $this->client->request('GET', '/team/problems');

                // Check that the correct menu item is selected
                $this->assertSelectorTextContains('.nav-item.active .nav-link',
                    'Problemset');

                // Get the card bodies and verify we have exatly three of them
                $cardBodies = $crawler->filter('.card-body');
                $this->assertSame(3, $cardBodies->count());

                for ($i = 0; $i < 3; $i++) {
                    $card = $cardBodies->eq($i);
                    $this->assertSame('Problem ' . $problems[$i],
                        $card->filter('.card-title')->text(null, true));
                    $this->assertSame($descriptions[$i],
                        $card->filter('h4.card-subtitle')->text(null, true));

                    if ($withLimits) {
                        $this->assertSame(
                            'Limits: 5 seconds / 2 GB',
                            $card->filter('h5.card-subtitle')->text(null, true)
                        );
                    } else {
                        $this->assertSame(0,
                            $card->filter('h5.card-subtitle')->count());
                    }

                    // Download the problem text and make sure it is correct
                    $problemTextLink = $card->selectLink('problem text');
                    ob_start();
                    $this->client->click($problemTextLink->link());
                    $content = ob_get_contents();
                    ob_end_clean();

                    $this->assertSame($problemTexts[$i], $content);
                }
            });
    }

    public function withLimitsProvider()
    {
        yield [false];
        yield [true];
    }

    /**
     * Test that the problems page shows only sample data
     */
    public function testSamples()
    {
        // First, enable two samples for the fltcmp problem
        $em = self::$container->get(EntityManagerInterface::class);
        /** @var Problem $problem */
        $problem = $em->getRepository(Problem::class)->findOneBy(['externalid' => 'fltcmp']);

        /** @var Testcase[] $samples */
        $samples = [
            $problem->getTestcases()->get(0),
            $problem->getTestcases()->get(2)
        ];
        $samples[0]->setSample(true);
        $samples[1]->setSample(true);
        $em->flush();

        $this->logIn();

        $crawler = $this->client->request('GET', '/team/problems');

        // Get the card bodies
        $cardBodies = $crawler->filter('.card-body');

        // The first and last card should not have any samples
        $this->assertSame(0,
            $cardBodies->eq(0)->filter('.list-group .list-group-item')->count());
        $this->assertSame(0,
            $cardBodies->eq(2)->filter('.list-group .list-group-item')->count());

        // The second card should contain three list items, one for each sample and one to download all samples
        $listItems = $cardBodies->eq(1)->filter('.list-group .list-group-item');
        $this->assertSame(3, $listItems->count());

        // Check that we have the correct links
        for ($i = 0; $i < 2; $i++) {
            $links = $listItems->eq($i)->filter('a');
            $this->assertSame(sprintf('input #%d', $i + 1),
                $links->eq(0)->text(null, true));
            $this->assertSame(sprintf('output #%d', $i + 1),
                $links->eq(1)->text(null, true));
            $this->assertSame(sprintf('/team/%d/sample/%d/input',
                $problem->getProbid(), $i + 1), $links->eq(0)->attr('href'));
            $this->assertSame(sprintf('/team/%d/sample/%d/output',
                $problem->getProbid(), $i + 1), $links->eq(1)->attr('href'));

            // Download the sample and make sure the contents are correct.
            // We use ob_ methods since this is a streamed response
            ob_start();
            $this->client->click($links->eq(0)->link());
            $content = ob_get_contents();
            ob_end_clean();
            $this->assertSame($samples[$i]->getContent()->getInput(), $content);

            ob_start();
            $this->client->click($links->eq(1)->link());
            $content = ob_get_contents();
            ob_end_clean();
            $this->assertSame($samples[$i]->getContent()->getOutput(),
                $content);

            // TODO: add tests for samples.zip: check that it is a ZIP file
            // and it contains the correct files.
        }

        // Check the link to download all samples
        $link = $listItems->eq(2)->filter('a')->first();
        $this->assertSame('zip with all samples', $link->text(null, true));
        $this->assertSame(sprintf('/team/%d/samples.zip',
            $problem->getProbid()),
            $link->attr('href'));

        // Now reset the sample status
        $em->clear();
        $problem = $em->getRepository(Problem::class)->findOneBy(['externalid' => 'fltcmp']);

        $problem->getTestcases()->get(0)->setSample(false);
        $problem->getTestcases()->get(1)->setSample(false);
        $em->flush();
    }
}
