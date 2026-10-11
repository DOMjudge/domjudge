<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\SampleSubmissionsFixture;
use App\Tests\Unit\BaseTestCase;
use Generator;

class SubmissionControllerTest extends BaseTestCase
{
    protected array         $roles   = ['jury'];
    protected static string $baseURL = '/jury/submissions';

    /**
     * Test that the basic building blocks of the index page are there.
     */
    public function testIndexBasic(): void
    {
        $this->verifyPageResponse('GET', static::$baseURL, 200);
    }

    /**
     * Test the filtered views do throw server errors.
     *
     * @dataProvider provideViews
     */
    public function testIndexViewFilter(string $filter, array $fixtures): void
    {
        $this->loadFixtures($fixtures);
        $this->verifyPageResponse('GET', static::$baseURL . '?view=' . $filter, 200);
    }

    public function provideViews(): Generator
    {
        foreach ([[], [SampleSubmissionsFixture::class]] as $fixtures) {
            foreach (['all', 'unjudged', 'unverified'] as $view) {
                yield [$view, $fixtures];
            }
        }
    }

    public function testFilterFormSameSite(): void
    {
        $this->submitResultFilter([]);
        self::assertSame(['correct'], json_decode($this->filterCookie() ?? 'null', true)['result'] ?? null);
    }

    public function testFilterFormRejectsCrossSite(): void
    {
        $this->submitResultFilter(['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        self::assertNull($this->filterCookie());
    }

    private function filterCookie(): ?string
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'domjudge_submissionsfilter') {
                return $cookie->getValue();
            }
        }
        return null;
    }

    /**
     * @param array<string, string> $server
     */
    private function submitResultFilter(array $server): void
    {
        $this->verifyPageResponse('GET', static::$baseURL, 200);
        $form = $this->getCurrentCrawler()->selectButton('Apply filters')->form();
        $this->client->submit($form, ['submissions_filter[result]' => ['correct']], $server);
    }
}
