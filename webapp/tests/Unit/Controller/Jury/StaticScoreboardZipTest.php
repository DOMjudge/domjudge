<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\SampleSubmissionsInBucketsFixture;
use App\Tests\Unit\BaseTestCase;

class StaticScoreboardZipTest extends BaseTestCase
{
    protected array $roles = ['admin'];

    protected static array $fixtures = [
        SampleSubmissionsInBucketsFixture::class,
    ];

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

    private function getSubmissionsDataFromStaticScoreboardZip(string $type): string
    {
        $uri = sprintf('/jury/contests/demo/%s-scoreboard.zip', $type);
        $this->client->request('GET', $uri);

        $response = $this->client->getInternalResponse();
        $content = $response->getContent();

        self::assertEquals(200, $response->getStatusCode(), $content . "\nURI = $uri");
        self::assertStringStartsWith('PK', $content, 'Expected a ZIP response.');

        $zipContents = $this->unzipString($content);
        self::assertArrayHasKey(
            'submissions-data.json',
            $zipContents,
            'ZIP entries: ' . implode(', ', array_keys($zipContents))
        );
        self::assertIsString($zipContents['submissions-data.json']);

        return $zipContents['submissions-data.json'];
    }
}
