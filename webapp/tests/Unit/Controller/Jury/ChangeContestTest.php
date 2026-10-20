<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Tests\Unit\BaseTestCase;

class ChangeContestTest extends BaseTestCase
{
    protected array $roles = ['jury'];

    // Switch between contests on a contest-scoped URL
    public function testChangeContestLocalReferer(): void
    {
        $this->client->request('GET', '/jury/change-contest/contestB', [], [], [
            'HTTP_REFERER' => 'http://localhost/jury/contests/contestA/submissions'
        ]);
        $response = $this->client->getResponse();
        self::assertTrue($response->isRedirect('http://localhost/jury/contests/contestB/submissions'));
        
        // Check cookie
        $cookies = $response->headers->getCookies();
        $found = false;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'domjudge_cid') {
                self::assertEquals('contestB', $cookie->getValue());
                $found = true;
            }
        }
        self::assertTrue($found);
    }

    // Switch between contests on a contest-scoped URL with query params
    public function testChangeContestWithQueryParams(): void
    {
        $this->client->request('GET', '/jury/change-contest/contestB', [], [], [
            'HTTP_REFERER' => 'http://localhost/jury/contests/contestA/submissions?filter=all'
        ]);
        $response = $this->client->getResponse();
        self::assertTrue($response->isRedirect('http://localhost/jury/contests/contestB/submissions?filter=all'));
    }

    // Switch between contests on a contest-scoped URL with no trailing path
    public function testChangeContestNoTrailingPath(): void
    {
        $this->client->request('GET', '/jury/change-contest/contestB', [], [], [
            'HTTP_REFERER' => 'http://localhost/jury/contests/contestA'
        ]);
        $response = $this->client->getResponse();
        self::assertTrue($response->isRedirect('http://localhost/jury/contests/contestB'));
    }

    // Switch between contests on a contest-scoped URL with no trailing path and query params
    public function testChangeContestWithQueryParamsNoTrailingPath(): void
    {
        $this->client->request('GET', '/jury/change-contest/contestB', [], [], [
            'HTTP_REFERER' => 'http://localhost/jury/contests/contestA?foo=bar'
        ]);
        $response = $this->client->getResponse();
        self::assertTrue($response->isRedirect('http://localhost/jury/contests/contestB?foo=bar'));
    }

    // Switch between contests on the page of a single submission or clarification, which
    // belongs to the old contest: go to the list of the new contest instead
    public function testChangeContestOnDetailPage(): void
    {
        foreach ([
            'submissions/42'                    => 'submissions',
            'submissions/42/source?fetch=0'     => 'submissions',
            'submissions/42/runs/7/team-output' => 'submissions',
            'clarifications/42'                 => 'clarifications',
            'clarifications/42#answer'          => 'clarifications',
            'clarifications/send'               => 'clarifications/send',
            'clarifications/send?team=3'        => 'clarifications/send?team=3',
        ] as $from => $to) {
            $this->client->request('GET', '/jury/change-contest/contestB', [], [], [
                'HTTP_REFERER' => 'http://localhost/jury/contests/contestA/' . $from
            ]);
            $response = $this->client->getResponse();
            self::assertTrue($response->isRedirect('http://localhost/jury/contests/contestB/' . $to),
                $from . ' redirects to ' . $response->headers->get('Location'));
        }
    }

    // Switch to a contest whose ID starts with a digit
    public function testChangeContestToNumericId(): void
    {
        $this->client->request('GET', '/jury/change-contest/2026', [], [], [
            'HTTP_REFERER' => 'http://localhost/jury/contests/contestA/submissions'
        ]);
        $response = $this->client->getResponse();
        self::assertTrue($response->isRedirect('http://localhost/jury/contests/2026/submissions'));
    }

    // Switch to "no contest" (-1) on a contest-scoped URL
    public function testChangeContestToNoContest(): void
    {
        $this->client->request('GET', '/jury/change-contest/-1', [], [], [
            'HTTP_REFERER' => 'http://localhost/jury/contests/contestA/submissions'
        ]);
        $response = $this->client->getResponse();
        self::assertTrue($response->isRedirect('/jury'));
    }

    // Switch contest on a non-contest-scoped URL
    public function testChangeContestNonContestScoped(): void
    {
        $this->client->request('GET', '/jury/change-contest/contestB', [], [], [
            'HTTP_REFERER' => 'http://localhost/jury/teams'
        ]);
        $response = $this->client->getResponse();
        self::assertTrue($response->isRedirect('http://localhost/jury/teams'));
    }
}
