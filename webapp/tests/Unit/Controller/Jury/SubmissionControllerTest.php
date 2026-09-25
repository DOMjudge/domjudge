<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use PHPUnit\Framework\Attributes\DataProvider;
use App\DataFixtures\Test\SampleSubmissionsFixture;
use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\SubmissionSource;
use App\Entity\Team;
use App\Service\SubmissionService;
use App\Tests\Unit\BaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SubmissionControllerTest extends BaseTestCase
{
    protected array         $roles   = ['jury'];
    protected static string $baseURL = '/jury/contests/demo/submissions';

    /**
     * Test that the basic building blocks of the index page are there.
     */
    public function testIndexBasic(): void
    {
        $this->verifyPageResponse('GET', static::$baseURL, 200);
    }

    /**
     * Test the filtered views do throw server errors.
     */
    #[DataProvider('provideViews')]
    public function testIndexViewFilter(string $filter, array $fixtures): void
    {
        $this->loadFixtures($fixtures);
        $this->verifyPageResponse('GET', static::$baseURL . '?view=' . $filter, 200);
    }

    public static function provideViews(): Generator
    {
        foreach ([[], [SampleSubmissionsFixture::class]] as $fixtures) {
            foreach (['all', 'unjudged', 'unverified'] as $view) {
                yield [$view, $fixtures];
            }
        }
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function addSubmission(): Submission
    {
        $em = $this->em();

        $message = null;
        return self::getContainer()->get(SubmissionService::class)->submitSolution(
            $em->getRepository(Team::class)->findOneBy(['name' => 'DOMjudge']),
            null,
            $em->getRepository(Problem::class)->findOneBy(['externalid' => 'hello']),
            $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']),
            'c',
            [new UploadedFile(__FILE__, 'foo.c', null, null, true)],
            SubmissionSource::UNKNOWN, null, null, null, null, null, $message
        );
    }

    /**
     * verifyPageResponse() cannot send a request body, so post the form directly.
     *
     * @param array<string, string> $parameters
     */
    private function postVerify(int $judgingId, array $parameters): void
    {
        // Send the referer a browser would: the page holding the form. BrowserKit would
        // otherwise reuse the previous request's URI, a POST-only route.
        $this->client->request(
            'POST',
            sprintf('/jury/%d/verify', $judgingId),
            $parameters,
            [],
            ['HTTP_REFERER' => 'http://localhost' . static::$baseURL]
        );
        $response = $this->client->getResponse();
        self::assertEquals(302, $response->getStatusCode(), (string)$response->getContent());
    }

    /**
     * The request wrote through its own entity manager; start from a clean identity map.
     */
    private function reloadJudging(int $judgingId): Judging
    {
        $em = $this->em();
        $em->clear();

        return $em->getRepository(Judging::class)->find($judgingId);
    }

    /**
     * Marking a judging verified records the verdict as checked and remembers which jury
     * member checked it; unmarking clears the jury member again.
     */
    public function testVerifyAndUnverifyJudging(): void
    {
        $judgingId = $this->addSubmission()->getJudgings()->first()->getJudgingid();

        $this->postVerify($judgingId, ['verified' => '1', 'comment' => 'looks right']);

        $judging = $this->reloadJudging($judgingId);
        self::assertTrue($judging->getVerified());
        self::assertSame('demo', $judging->getJuryMember());
        self::assertSame('looks right', $judging->getVerifyComment());

        $this->postVerify($judgingId, ['verified' => '0']);

        $judging = $this->reloadJudging($judgingId);
        self::assertFalse($judging->getVerified());
        self::assertNull($judging->getJuryMember(), 'unverifying must release the claim');
    }
}
