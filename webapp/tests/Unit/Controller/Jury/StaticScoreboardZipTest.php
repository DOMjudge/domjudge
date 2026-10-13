<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\SampleSubmissionsInBucketsFixture;
use App\Entity\Contest;
use App\Service\ScoreboardService;
use App\Tests\Unit\BaseTestCase;
use Doctrine\ORM\EntityManagerInterface;

class StaticScoreboardZipTest extends BaseTestCase
{
    protected array $roles = ['admin'];

    protected static array $fixtures = [
        SampleSubmissionsInBucketsFixture::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        static::getContainer()->get(ScoreboardService::class)->refreshCache($contest);
    }

    public function testUnfrozenStaticScoreboardZipCarriesUnfrozenSubmissionData(): void
    {
        $publicSubmissionData = $this->getSubmissionsDataFromStaticScoreboardZip('public');
        $unfrozenSubmissionData = $this->getSubmissionsDataFromStaticScoreboardZip('unfrozen');

        $publicPendingCount = substr_count($publicSubmissionData, 'sol_queued');
        $unfrozenPendingCount = substr_count($unfrozenSubmissionData, 'sol_queued');

        self::assertGreaterThan(
            0,
            $publicPendingCount,
            'The fixture should contain submissions hidden by the public scoreboard freeze.'
        );
        self::assertEquals(
            0,
            $unfrozenPendingCount,
            'Unfrozen static scoreboard ZIP should reveal final verdicts in submissions-data.json.'
        );
    }

    public function testStaticScoreboardZipOnlyUsesRelativeLinks(): void
    {
        $zipContents = $this->getStaticScoreboardZipContents('public');

        self::assertArrayHasKey('index.html', $zipContents);
        self::assertArrayHasKey('scoreboard-category-color.css', $zipContents);

        $index = $zipContents['index.html'];
        self::assertStringNotContainsString('CHANGE_ME', $index);
        self::assertStringNotContainsString('../', $index);
        self::assertDoesNotMatchRegularExpression('/(href|src)="\//', $index);
        self::assertStringContainsString('href="scoreboard-category-color.css"', $index);
        self::assertStringContainsString('data-submissions-url="submissions-data.json"', $index);
    }

    private function getSubmissionsDataFromStaticScoreboardZip(string $type): string
    {
        $zipContents = $this->getStaticScoreboardZipContents($type);
        self::assertArrayHasKey(
            'submissions-data.json',
            $zipContents,
            'ZIP entries: ' . implode(', ', array_keys($zipContents))
        );
        self::assertIsString($zipContents['submissions-data.json']);

        return $zipContents['submissions-data.json'];
    }

    private function getStaticScoreboardZipContents(string $type): array
    {
        $uri = sprintf('/jury/contests/demo/%s-scoreboard.zip', $type);
        $this->client->request('GET', $uri);

        $response = $this->client->getInternalResponse();
        $content = $response->getContent();

        self::assertEquals(200, $response->getStatusCode(), $content . "\nURI = $uri");
        self::assertStringStartsWith('PK', $content, 'Expected a ZIP response.');

        return $this->unzipString($content);
    }
}
