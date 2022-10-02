<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\RejudgingStatesFixture;
use App\Entity\Contest;
use App\Tests\Unit\BaseTest;
use Generator;

class RejudgingControllerTest extends BaseTest
{
    protected array $roles = ['admin'];

    /**
     * @dataProvider provideRoles
     */
    public function testStartPage(array $roles, int $http): void
    {
        $this->roles = $roles;
        $this->logOut();
        if ($roles) {
            $this->logIn();
        }
        $this->verifyPageResponse('GET', '/jury/rejudgings', $http);
        if ($http===200) {
            foreach (['No rejudgings defined',' Add new rejudging','Rejudgings'] as $element) {
                self::assertSelectorExists('body:contains("'.$element.'")');
            }
        }
    }

    /**
     * Provide the HTTP access code for the DOMjudge role
     */
    public function provideRoles(): Generator
    {
        yield [[],302];
        foreach (['team','balloon','clarification_rw'] as $role) {
            yield [[$role],403];
        }
        foreach (['jury','admin'] as $role) {
            yield [[$role],200];
        }
    }

    public function testCorrectSorting(): void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();
        $this->loadFixture(RejudgingStatesFixture::class);
        $this->verifyPageResponse('GET', '/jury/rejudgings', 200);
        // The sorting is done in JS (and cannot be tested), this is the inverse ordering of the Fixture.
        foreach (['Canceled','Finished','0Percent_2','0Percent_1','Unit'] as $index => $reason) {
            self::assertSelectorExists('tr:nth-child('.($index+1).'):contains("'.$reason.'")');
        }
    }

    public function setRejudgingState(?string $contestName): void
    {
        $em = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        if ($contestName === null) {
            $cid = -1;
        } else {
            /** @var Contest $contest */
            $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => $contestName]);
            $contest = $contest->setDeactivatetimeString("'2099-01-02 07:07:07'");
            $cid = $contest->getCid();
        }
        $this->loadFixture(RejudgingStatesFixture::class);
        $this->client->request('GET', '/team/change-contest/' . $cid);
    }

    /**
     * @dataProvider provideShownRejudgings
     **/
    public function testRejudgingCurrentContest(
        ?string $contestName,
        array $shown,
        array $hidden,
        int $todo
    ): void {
        $this->setRejudgingState($contestName);
        $this->verifyPageResponse('GET', '/jury/rejudgings', 200);
        foreach ($shown as $rejudging) {
            self::assertSelectorExists('body:contains("' . $rejudging . '")');
        }
        foreach ($hidden as $rejudging) {
            self::assertSelectorNotExists('body:contains("' . $rejudging . '")');
        }
    }

    /**
     * @dataProvider provideShownRejudgings
     **/
    public function testRejudgingCounterMenu(
        ?string $contestName,
        array $shown,
        array $hidden,
        int $todo
    ): void {
        $DOMselector = '#menu_rejudgings';
        $this->setRejudgingState($contestName);
        $this->verifyPageResponse('GET', '/jury', 200);
        self::assertSelectorTextContains($DOMselector, 'rejudgings');
        // We cannot count the amount of rejudgings here.

        // We check the page where the data comes from.
        $this->client->request('GET', '/jury/updates');
        $response = $this->client->getResponse();
        $jsonResponse = json_decode($response->getContent(), true);
        $this->assertEquals($todo, count($jsonResponse['rejudgings']));
    }

    public function provideShownRejudgings(): Generator
    {
        // The case where no contest is active/chosen/current.
        $show = [];
        foreach (RejudgingStatesFixture::rejudgingStages() as $stage) {
            $show[] = $stage[0];
        }
        yield [null, $show, [], 3];

        // Rejudging during a contest
        foreach (['demo'] as $contestName) {
            $show = [];
            $hidden = [];
            $todo = 0;
            foreach (RejudgingStatesFixture::rejudgingStages() as $stage) {
                if (in_array($contestName, $stage[4])) {
                    $show[] = $stage[0];
                    if ($stage[1] === null) {
                        $todo++;
                    }
                } else {
                    $hidden[] = $stage[0];
                }
            }
            yield [$contestName, $show, $hidden, $todo];
        }
    }
}
