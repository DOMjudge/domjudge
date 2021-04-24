<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\RejudgingStatesFixture;
use App\Entity\Contest;
use App\Tests\Unit\BaseTest;
use Generator;

class RejudgingControllerTest extends BaseTest
{
    protected $roles = ['admin'];

    /**
     * @dataProvider provideRoles
     */
    public function testStartPage(array $roles, int $http): void
    {
        $this->roles = $roles;
        $this->logOut();
        $this->logIn();
        $this->verifyPageResponse('GET', '/jury/rejudgings', $http);
        if($http===200) {
            foreach(['No rejudgings defined',' Add new rejudging','Rejudgings'] as $element) {
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
        foreach(['team','balloon','clarification_rw'] as $role) {
            yield [[$role],403];
        }
        foreach(['jury','admin'] as $role) {
            yield [[$role],200];
        }
    }

    public function testCorrectSorting() : void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();
        $this->loadFixture(RejudgingStatesFixture::class);
        $this->verifyPageResponse('GET', '/jury/rejudgings', 200);
        // The sorting is done in JS (and cannot be tested), this is the inverse ordering of the Fixture
        foreach(['Canceled','Finished','0Percent_2','0Percent_1','Unit','MultiContest'] as $index=>$reason)
        {
            self::assertSelectorExists('tr:nth-child('.($index+1).'):contains("'.$reason.'")');
        }
    }

    /**
     * @dataProvider provideShownRejudgings
     **/
    public function testRejudgingCurrentContest(?string $contestName, array $shown, array $hidden): void
    {
        $em = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        if ($contestName === NULL) {
            $cid = -1;
        } else {
            /** @var Contest $contest */
            $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => $contestName]);
            $contest = $contest->setDeactivatetimeString("'2099-01-02 07:07:07'");
            $cid = $contest->getCid();
        }
        $this->loadFixture(RejudgingStatesFixture::class);
        $this->client->request('GET', '/team/change-contest/' . $cid);
        $this->verifyPageResponse('GET', '/jury/rejudgings', 200);
        foreach($shown as $rejudging) {
            self::assertSelectorExists('body:contains("' . $rejudging . '")');
        }
        foreach($hidden as $rejudging) {
            self::assertSelectorNotExists('body:contains("' . $rejudging . '")');
        }
    }

    public function provideShownRejudgings(): Generator
    {
        // The case where no contest is active/chosen/current
        $show = [];
        foreach(RejudgingStatesFixture::rejudgingStages() as $stage){
            $show[] = $stage[0];
        }
        yield [Null, $show, []];

        // Rejudging during a contest
        foreach(['demoprac','demo'] as $contestName) {
            $show = [];
            $hidden = [];
            foreach(RejudgingStatesFixture::rejudgingStages() as $stage){
                if(in_array($contestName, $stage[4])){
                    $show[] = $stage[0];
                } else {
                    $hidden[] = $stage[0];
                }
            }
            yield [$contestName, $show, $hidden];
        }
    }
}
