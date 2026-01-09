<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\SubmissionRateLimitFixture;
use App\Entity\Problem;
use App\Entity\Contest;
use App\Tests\Unit\BaseTestCase as UnitBaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SubmissionRateLimitTest extends UnitBaseTestCase
{
    protected ?string $apiEndpoint = 'submissions';

    protected function getDemoContestId(): string
    {
        /** @var Contest $contest */
        $contest = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Contest::class)
            ->findOneBy(['shortname' => 'demo']);
        return $contest->getExternalid();
    }

    protected function verifyApiRequest(
        string $method,
        string $apiUri,
        int $status,
        string $user,
        string $password,
        mixed $jsonData = null,
        array $files = []
    ): mixed {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'PHP_AUTH_USER' => $user,
            'PHP_AUTH_PW' => $password,
        ];

        $this->client->request($method, '/api' . $apiUri, [], $files, $server, $jsonData ? json_encode($jsonData) : null);
        $response = $this->client->getResponse();

        static::assertEquals($status, $response->getStatusCode(),
            sprintf("\nUnexpected status code: %d (expected %d)\nURL: %s\nUser: %s\nResponse: %s\n",
                $response->getStatusCode(), $status, $apiUri, $user, $response->getContent()));
        return json_decode($response->getContent(), true);
    }

    protected function submitSolution(string $user, string $password, int $expectedStatus = 200): mixed
    {
        $contestId = $this->getDemoContestId();
        $apiEndpoint = $this->apiEndpoint;
        $problemId = $this->resolveEntityId(Problem::class, '1');
        $data = [
            'problem' => $problemId,
            'language' => 'cpp',
        ];
        $files = ['code' => new UploadedFile(__FILE__, 'test.cpp')];

        return $this->verifyApiRequest('POST', "/contests/$contestId/$apiEndpoint", $expectedStatus, $user, $password, $data, $files);
    }

    public function testRateLimitEnforcement(): void
    {
        $this->loadFixture(SubmissionRateLimitFixture::class);

        // Set limit to 3. 3 submissions should succeed, 4th should fail.
        $this->withChangedConfiguration('submission_rate_limit', ['10' => '3'], function (): void {
            // First 3 submissions should succeed
            $this->submitSolution('ratelimit-user', 'ratelimit-password', 200);
            $this->submitSolution('ratelimit-user', 'ratelimit-password', 200);
            $this->submitSolution('ratelimit-user', 'ratelimit-password', 200);

            // Fourth submission should fail with 400 Bad Request
            $response = $this->submitSolution('ratelimit-user', 'ratelimit-password', 400);

            static::assertStringContainsString('Submission limit reached', $response['message']);
            static::assertStringContainsString('3 submissions per 10 seconds', $response['message']);
        });
    }

    public function testMultipleIntervalsMiddleViolation(): void
    {
        $this->loadFixture(SubmissionRateLimitFixture::class);

        // Config: 1 sub per 10s, 2 subs per 60s, 10 subs per 3600s
        $config = ['10' => '1', '60' => '2', '3600' => '10'];
        $this->withChangedConfiguration('submission_rate_limit', $config, function (): void {
            // 1st submission
            $this->submitSolution('ratelimit-user', 'ratelimit-password', 200);

            // Wait 11s to clear the 10s window but stay within the 60s window
            sleep(11);

            // 2nd submission (t=11s after 1st)
            // 10s window: OK (0 existing), 60s window: OK (1 existing)
            $this->submitSolution('ratelimit-user', 'ratelimit-password', 200);

            // Wait another 11s
            sleep(11);

            // 3rd submission (t=22s after 1st, t=11s after 2nd)
            // 10s window: OK (0 existing)
            // 60s window: FAIL (2 existing within 60s)
            // 3600s window: OK (2 existing)
            $response = $this->submitSolution('ratelimit-user', 'ratelimit-password', 400);

            static::assertStringContainsString('Submission limit reached', $response['message']);
            static::assertStringContainsString('2 submissions per 1 minute allowed', $response['message']);
        });
    }

    public function testJuryBypassRateLimit(): void
    {
        $this->loadFixture(SubmissionRateLimitFixture::class);

        $this->withChangedConfiguration('submission_rate_limit', ['3600' => '1'], function (): void {
            // Jury (ratelimit-jury) should be able to submit multiple times
            $this->submitSolution('ratelimit-jury', 'ratelimit-jury-password', 200);
            $this->submitSolution('ratelimit-jury', 'ratelimit-jury-password', 200);
        });
    }
}
