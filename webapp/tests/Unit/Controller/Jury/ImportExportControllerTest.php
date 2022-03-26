<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Tests\Unit\BaseTest;
use Generator;

class ImportExportControllerTest extends BaseTest
{
    protected array $roles = ['admin'];

    /**
     * Test that the basic building blocks of the index page are there.
     */
    public function testIndexBasic(): void
    {
        $this->verifyPageResponse('GET', '/jury/import-export', 200);

        $sections = ['Problems', 'Contests', 'Teams & groups', 'Results'];
        foreach ($sections as $section) {
            self::assertSelectorExists(sprintf('h2:contains("%s")', $section));
        }
        self::assertSelectorExists('small:contains(\'Create a "Web Services Token"\')');

        // We've reached the end of the page.
        self::assertSelectorExists('li:contains("results.tsv")');
    }

    /**
     * Test that the expected contest dropdowns on the index page are present.
     *
     * @dataProvider provideContests
     */
    public function testIndexContestDropdowns(string $contest): void
    {
        $this->verifyPageResponse('GET', '/jury/import-export', 200);

        self::assertSelectorExists(sprintf('select#problem_upload_contest > option:contains("%s")', $contest));
        self::assertSelectorExists(sprintf('select#contest_export_contest > option:contains("%s")', $contest));
    }

    /**
     * Test that the expected dynamic items on the index page are present.
     *
     * @dataProvider provideSortOrders
     */
    public function testIndexGeneratedItems(string $sortOrder): void
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

    /**
     * Test that submit buttons show an icon.
     */
    public function testIndexButtonsHaveIcons(): void
    {
        $this->verifyPageResponse('GET', '/jury/import-export', 200);

        self::assertSelectorExists('button#problem_upload_upload i.fa.fa-upload');
        self::assertSelectorExists('button#contest_export_export i.fa.fa-download');
    }

    /**
     * Test export of contest.yaml.
     *
     * @dataProvider provideContestYamlContents
     */
    public function testContestExport(string $cid, string $expectedYaml): void
    {
        $this->verifyPageResponse('GET', '/jury/import-export', 200);
        ob_start();
        $this->client->submitForm('contest_export_export', ['contest_export[contest]'=>$cid]);

        $content = ob_get_contents();
        ob_end_clean();

        $this->assertEquals($expectedYaml, $content);
    }

    public function provideContestYamlContents(): Generator
    {
        $yaml =<<<HEREDOC
name: 'Demo contest'
short-name: demo
start-time: '2021-01-01T11:00:00+00:00'
duration: '26285:00:00.000'
scoreboard-freeze-duration: '1:00:00'
penalty-time: 20
default-clars:
    - 'No comment.'
    - 'Read the problem statement carefully.'
clar-categories:
    - 'General issue'
    - 'Technical issue'
languages:
    -
        name: Ada
    -
        name: AWK
    -
        name: 'Bash shell'
    -
        name: C
    -
        name: C++
    -
        name: 'C#'
    -
        name: Fortran
    -
        name: Haskell
    -
        name: Java
    -
        name: JavaScript
    -
        name: Kotlin
    -
        name: Lua
    -
        name: Pascal
    -
        name: Perl
    -
        name: Prolog
    -
        name: 'Python 3'
    -
        name: R
    -
        name: Ruby
    -
        name: Scala
    -
        name: 'POSIX shell'
    -
        name: Swift
problems:
    -
        label: boolfind
        letter: boolfind
        name: 'Boolean switch search'
        short-name: boolfind
        color: green
        rgb: '#008000'
    -
        label: fltcmp
        letter: fltcmp
        name: 'Float special compare test'
        short-name: fltcmp
        color: indianred
        rgb: '#CD5C5C'
    -
        label: hello
        letter: hello
        name: 'Hello World'
        short-name: hello
        color: skyblue
        rgb: '#87CEEB'

HEREDOC;
        yield ["2", $yaml];
    }

    /**
     * Test export of groups.tsv and teams.tsv.
     *
     * @dataProvider provideTsvContents
     */
    public function testGroupsTeamsTsvExport(string $linkname, string $expectedData): void
    {
        $this->verifyPageResponse('GET', '/jury/import-export', 200);
        $link = $this->getCurrentCrawler()->filter($linkname)->link();
        ob_start();
        $this->client->click($link);
        $content = ob_get_contents();
        ob_end_clean();

        $this->assertEquals($expectedData, $content);
    }

    public function provideTsvContents(): Generator
    {
        yield ['a:contains("teams.tsv")', 'teams	1
2	exteam	3	Example teamname	Utrecht University	UU	NLD	utrecht
'];
        yield ['li:contains("results.tsv") a:contains("for sort order 0")', 'results	1
2	1	Gold Medal	0	0	0	Participants
'];
        yield ['li:contains("results.tsv") a:contains("for sort order 1")', 'results	1
'];
        yield ['a:contains("groups.tsv")', 'groups	1
2	Self-Registered
3	Participants
4	Observers
'];
    }

    /**
     * Test export of clarifications.html.
     */
    public function testClarificationsHtmlExport(): void
    {
        $this->verifyPageResponse('GET', '/jury/import-export', 200);
        $link = $this->getCurrentCrawler()->filter('a:contains("clarifications.html")')->link();
        $this->client->click($link);
        self::assertSelectorExists('h1:contains("Clarifications for Demo contest")');
        self::assertSelectorExists('td:contains("Example teamname")');
        self::assertSelectorExists('td:contains("hello: Hello World")');
        self::assertSelectorExists('pre:contains("Can you tell me how to solve this problem?")');
        self::assertSelectorExists('pre:contains("No, read the problem statement.")');
    }

    /**
     * Test export of results.html.
     */
    public function testResultsHtmlExport(): void
    {
        $this->verifyPageResponse('GET', '/jury/import-export', 200);
        $link = $this->getCurrentCrawler()->filter('li:contains("results.html") a:contains("for sort order 0")')->link();
        $this->client->click($link);
        self::assertSelectorExists('h1:contains("Results for Demo contest")');
        self::assertSelectorExists('th:contains("Example teamname")');
        self::assertSelectorExists('th:contains("hello: Hello World")');
    }
}
