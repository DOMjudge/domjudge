<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\ClarificationFixture;
use App\DataFixtures\Test\DemoPreStartContestFixture;
use App\Tests\Unit\BaseTestCase;
use Generator;

class ImportExportControllerTest extends BaseTestCase
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
        self::assertSelectorExists('div.help-text:contains(\'Create a "Web Services Token"\')');
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

    public function provideContests(): Generator
    {
        yield ['Demo contest'];
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
        $this->loadFixtures([DemoPreStartContestFixture::class]);
        $this->verifyPageResponse('GET', '/jury/import-export', 200);
        $this->client->submitForm('contest_export_export', ['contest_export[contest]'=>$cid]);
        static::assertEquals($expectedYaml, $this->client->getInternalResponse()->getContent());
    }

    public function provideContestYamlContents(): Generator
    {
        $year = date('Y')+1;
        $pastYear = date('Y');
        $yaml =<<<HEREDOC
id: demo
formal_name: 'Demo contest'
name: demo
start_time: '{$year}-01-01T08:00:00+00:00'
end_time: '{$year}-01-01T13:00:00+00:00'
duration: '5:00:00.000'
penalty_time: 20
activate_time: '{$pastYear}-01-01T08:00:00+00:00'
medals:
    gold: 4
    silver: 4
    bronze: 4
scoreboard_freeze_time: '{$year}-01-01T12:00:00+00:00'
scoreboard_freeze_duration: '1:00:00'
problems:
    -
        id: hello
        label: A
        letter: A
        name: 'Hello World'
        color: mediumpurple
        rgb: '#9486EA'
    -
        id: fltcmp
        label: B
        letter: B
        name: 'Float special compare test'
        color: orangered
        rgb: '#E93603'
    -
        id: boolfind
        label: C
        letter: C
        name: 'Boolean switch search'
        color: saddlebrown
        rgb: '#9B630C'

HEREDOC;
        yield ["1", $yaml];
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
        $this->client->click($link);

        static::assertEquals($expectedData, $this->client->getInternalResponse()->getContent());
    }

    public function provideTsvContents(): Generator
    {
        yield ['a:contains("teams.tsv")', 'teams	1
exteam	exteam	participants	Example teamname	Utrecht University	UU	NLD	utrecht
'];
        yield ['a:contains("groups.tsv")', 'groups	1
self-registered	Self-Registered
participants	Participants
observers	Observers
'];
    }

    /**
     * Test export of clarifications.html.
     */
    public function testClarificationsHtmlExport(): void
    {
        $this->loadFixture(ClarificationFixture::class);
        $this->verifyPageResponse('GET', '/jury/import-export', 200);
        $link = $this->getCurrentCrawler()->filter('a:contains("clarifications.html")')->link();
        $this->client->click($link);
        self::assertSelectorExists('h1:contains("Clarifications for Demo contest")');
        self::assertSelectorExists('td:contains("Example teamname")');
        self::assertSelectorExists('td:contains("A: Hello World")');
        self::assertSelectorExists('div:contains("Is it necessary to read the problem statement carefully?")');
        self::assertSelectorExists('div:contains("Lunch is served")');
    }

    /**
     * Test export of results.html
     *
     * @dataProvider provideResultsHtmlExport
     */
    public function testResultsHtmlExport(bool $individuallyRanked, bool $honors, string $format): void
    {
        $this->loadFixture(ClarificationFixture::class);
        $this->verifyPageResponse('GET', '/jury/import-export', 200);
        $this->client->submitForm('export_results_export', [
            'export_results[sortorder]' => 0,
            'export_results[individually_ranked]' => (int)$individuallyRanked,
            'export_results[honors]' => (int)$honors,
            'export_results[format]' => $format,
        ]);
        self::assertSelectorExists('h1:contains("Results for Demo contest")');
        self::assertSelectorExists('th:contains("A: Hello World")');
        self::assertSelectorExists('td:contains("Example teamname")');
    }

    public function provideResultsHtmlExport(): Generator
    {
        yield [true, true, 'html_inline'];
        yield [true, false, 'html_inline'];
        yield [false, true, 'html_inline'];
        yield [false, true, 'html_inline'];
        yield [true, true, 'html_download'];
        yield [true, false, 'html_download'];
        yield [false, true, 'html_download'];
        yield [false, true, 'html_download'];
    }

    /**
     * Test export of results.tsv
     *
     * @dataProvider provideResultsTsvExport
     */
    public function testResultsTsvExport(
        int $sortOrder,
        bool $individuallyRanked,
        bool $honors,
        string $expectedData
    ): void {
        $this->loadFixture(ClarificationFixture::class);
        $this->verifyPageResponse('GET', '/jury/import-export', 200);
        $this->client->submitForm('export_results_export', [
            'export_results[sortorder]' => $sortOrder,
            'export_results[individually_ranked]' => (int)$individuallyRanked,
            'export_results[honors]' => (int)$honors,
            'export_results[format]' => 'tsv',
        ]);

        static::assertEquals($expectedData, $this->client->getInternalResponse()->getContent());
    }

    public function provideResultsTsvExport(): Generator
    {
        yield [0, true, true, 'results	1
exteam		Honorable	0	0	0	
'];
        yield [0, true, false, 'results	1
exteam		Honorable	0	0	0	
'];
        yield [0, false, true, 'results	1
exteam		Honorable	0	0	0	
'];
        yield [0, false, true, 'results	1
exteam		Honorable	0	0	0	
'];
        yield [1, true, true, 'results	1
'];
        yield [1, true, false, 'results	1
'];
        yield [1, false, true, 'results	1
'];
        yield [1, false, true, 'results	1
'];
    }
}
