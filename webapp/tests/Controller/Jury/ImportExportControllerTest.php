<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Tests\BaseTest;
use Generator;

class ImportExportControllerTest extends BaseTest
{
    protected static $roles = ['admin'];

    /**
     * Test that the basic building blocks of the index page are there.
     */
    public function testIndexBasic() : void
    {
        $this->verifyPageResponse('GET', '/jury/import-export', 200);

        $sections = ['Problems', 'Contests', 'Teams & groups', 'Results'];
        foreach ($sections as $section) {
            self::assertSelectorExists(sprintf('h2:contains("%s")', $section));
        }
        self::assertSelectorExists('small:contains(\'Create a "Web Services Token"\')');

        // We've reached the end of the page.
        self::assertSelectorExists('li:contains("scoreboard.tsv")');
    }

    /**
     * Test that the expected contest dropdowns on the index page are present
     *
     * @dataProvider provideContests
     */
    public function testIndexContestDropdowns(string $contest) : void
    {
        $this->verifyPageResponse('GET', '/jury/import-export', 200);

        self::assertSelectorExists(sprintf('select#problem_upload_multiple_contest > option:contains("%s")', $contest));
        self::assertSelectorExists(sprintf('select#contest_export_contest > option:contains("%s")', $contest));
    }

    /**
     * Test that the expected dynamic items on the index page are present
     *
     * @dataProvider provideSortOrders
     */
    public function testIndexGeneratedItems(string $sortOrder) : void
    {
        $this->verifyPageResponse('GET', '/jury/import-export', 200);

        self::assertSelectorExists(sprintf('li:contains("for sort order %s")', $sortOrder));
    }

    public function provideContests(): Generator
    {
        yield ['Demo contest'];
        yield ['Demo practice session'];
    }

    public function provideSortOrders(): Generator
    {
        yield ['0'];
        yield ['1'];
    }
}
