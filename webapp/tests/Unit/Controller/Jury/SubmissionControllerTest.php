<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\SampleSubmissionsFixture;
use App\Tests\Unit\BaseTestCase;
use Generator;

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
}
