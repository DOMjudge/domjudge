<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\PreviousSubmissionTestcaseRunsFixture;
use App\Entity\Judging;
use App\Entity\Submission;
use App\Tests\Unit\BaseTestCase;

class SubmissionPreviousComparisonTest extends BaseTestCase
{
    protected array $roles = ['jury'];

    public function testPreviousSubmissionTestcaseResultsDifferFromCurrent(): void
    {
        $this->loadFixture(PreviousSubmissionTestcaseRunsFixture::class);
        $submitId = $this->resolveReference(
            PreviousSubmissionTestcaseRunsFixture::CURRENT_SUBMISSION_REFERENCE,
            Submission::class,
            true,
        );

        $this->verifyPageResponse('GET', sprintf('/jury/contests/demo/submissions/%s', $submitId), 200);

        $currentRows = '//tr[not(contains(concat(" ", normalize-space(@class), " "), " lasttcruns "))]';
        $previousRows = '//tr[contains(concat(" ", normalize-space(@class), " "), " lasttcruns ")]';

        self::assertGreaterThan(0, $this->countResultLinksInRows($currentRows, Judging::RESULT_CORRECT));
        self::assertGreaterThan(0, $this->countResultLinksInRows($previousRows, 'wrong-answer'));
        self::assertSame(0, $this->countResultLinksInRows($previousRows, Judging::RESULT_CORRECT));
    }

    private function countResultLinksInRows(string $rowXPath, string $result): int
    {
        return $this->getCurrentCrawler()
            ->filterXPath(sprintf('%s//a[contains(@title, "result: %s")]', $rowXPath, $result))
            ->count();
    }
}
