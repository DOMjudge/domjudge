<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\RejudgingStatesFixture;
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
        foreach(['Canceled','Finished','0Percent_2','0Percent_1','Unit'] as $index=>$reason)
        {
            self::assertSelectorExists('tr:nth-child('.($index+1).'):contains("'.$reason.'")');
        }
    }
}
