<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\SampleSubmissionsFixture;
use App\Tests\Unit\BaseTest;
use Generator;

class SubmissionControllerTest extends BaseTest
{
    protected $roles = ['jury'];
    protected static $baseURL = '/jury/submissions';

    /**
     * Test that the basic building blocks of the index page are there.
     */
    public function testIndexBasic(): void
    {
        $this->verifyPageResponse('GET', static::$baseURL, 200);
    }

    /**
     * Test the filtered views have correct queries
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
            foreach (['all', 'unjudged', 'unverified', 'newest'] as $view) {
                yield [$view, $fixtures];
            }
        }
    }

    /*
     * When lazy evaluation is enabled testcases are evaluated until a non-correct
     * result is found. This tests that enabling further evaluation does not trigger
     * the evaluation for other submissions
     * @dataProvider provideSubmissions
     */
    //public function JudgeRemaining(): void
    //{
    //    // Load wrong submissions
    //    #$this->loadFixture("SampleSubmissionsFixture");
    //    self::assertEquals('yes','yes');
    //    // Check that for the first 2 the button is not available
    //    // Check that for the 2nd 2 the button is there
    //    // Check that when pressing the 1st button the 2nd is still available 
    //}
}
