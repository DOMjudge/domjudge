<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Team;

use App\Tests\Unit\BaseTestCase;

class SubmissionViewTest extends BaseTestCase
{
    protected array $roles = ['team'];

    /**
     * A submission that does not exist or belongs to another team has no judging for this
     * team. The page should say so, also when sample output is shown.
     */
    public function testUnknownSubmissionWithSampleOutput(): void
    {
        $this->withChangedConfiguration('show_sample_output', true, function (): void {
            $this->verifyPageResponse('GET', '/team/submission/4242', 200);
            static::assertStringContainsString(
                'Submission not found for this team or not judged yet.',
                $this->client->getResponse()->getContent()
            );
        });
    }
}
