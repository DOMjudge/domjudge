<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Team;

use App\DataFixtures\Test\SubmissionRateLimitFixture;
use App\Entity\Problem;
use App\Entity\Contest;
use App\Tests\Unit\BaseTestCase as UnitBaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SubmissionRateLimitUITest extends UnitBaseTestCase
{
    protected function submitSolution(string $user, string $password): void
    {
        $contest = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Contest::class)
            ->findOneBy(['shortname' => 'demo']);
        $contestId = $contest->getExternalid();
        
        $problemId = $this->resolveEntityId(Problem::class, '1');
        
        $url = "/api/contests/$contestId/submissions";
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'PHP_AUTH_USER' => $user,
            'PHP_AUTH_PW' => $password,
        ];
        $data = [
            'problem' => $problemId,
            'language' => 'cpp',
        ];
        $files = ['code' => new UploadedFile(__FILE__, 'test.cpp')];

        $this->client->request('POST', $url, [], $files, $server, json_encode($data));
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());
    }

    protected function loginAsTestTeam(string $username, string $password): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Sign in', [
            '_username' => $username,
            '_password' => $password,
        ]);
        static::assertTrue($this->client->getResponse()->isRedirect());
        $this->client->followRedirect();
    }

    public function testRateLimitWarningInUI(): void
    {
        $this->loadFixture(SubmissionRateLimitFixture::class);

        $config = ['60' => '1'];
        $this->withChangedConfiguration('submission_rate_limit', $config, function (): void {
            $this->loginAsTestTeam('ratelimit-user', 'ratelimit-password');

            // Initially, no warning should be there
            $this->client->request('GET', '/team/submit');
            static::assertEquals(200, $this->client->getResponse()->getStatusCode());
            static::assertStringNotContainsString('Submission limit reached', $this->client->getResponse()->getContent());

            // Submit once
            $this->submitSolution('ratelimit-user', 'ratelimit-password');

            // Now the warning should be there
            $this->client->request('GET', '/team/submit');
            static::assertEquals(200, $this->client->getResponse()->getStatusCode());
            static::assertStringContainsString('Submission limit reached', $this->client->getResponse()->getContent());
            static::assertStringContainsString('Maximum of 1 submission(s) per 1 minute allowed', $this->client->getResponse()->getContent());
        });
    }

    public function testRateLimitWarningInModal(): void
    {
        $this->loadFixture(SubmissionRateLimitFixture::class);

        $config = ['60' => '1'];
        $this->withChangedConfiguration('submission_rate_limit', $config, function (): void {
            $this->loginAsTestTeam('ratelimit-user', 'ratelimit-password');

            // Submit once
            $this->submitSolution('ratelimit-user', 'ratelimit-password');

            // Check modal (AJAX request)
            $this->client->request('GET', '/team/submit', [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
            static::assertEquals(200, $this->client->getResponse()->getStatusCode());
            static::assertStringContainsString('Submission limit reached', $this->client->getResponse()->getContent());
            static::assertStringContainsString('Maximum of 1 submission(s) per 1 minute allowed', $this->client->getResponse()->getContent());
        });
    }
}
