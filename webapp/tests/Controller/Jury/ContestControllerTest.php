<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Entity\Contest;

class ContestControllerTest extends JuryControllerTest
{
    protected static $baseUrl         = '/jury/contests';
    protected static $shortTag        = 'contest';
    protected static $deleteEntities  = array('name' => ['Demo practice session']);
    protected static $getIDFunc       = 'getCid';
    protected static $exampleEntries  = ['Demo contest','Demo practice session'];

    protected static $DOM_elements    = array('h1' => ['Contests'],
                                              'h3' => array('admin' => ['Current contests', 'All available contests'],
                                                            'jury' => []));
    protected static $className       = Contest::class;

    /*public function pRrovideAddEntity () : \Generator
    {
        $timeStrings = $this->getTimes();

        $contests  = [
            'NewContest',
            '⛀⛁⛂⛃⛄⛅⛆⛇⛈⛉⛊⛋⛌⛍⛎⛏',
            'name with spaces',
            str_repeat("A", 255)
        ];

        foreach ($contests as $contest) {
            yield [$contest,'11111', $timeStrings[0]];
        }
        // Try all possible values for the radio buttons
        // TODO: In the future test if the config option also works
        for($i=0; $i<2**5; $i++)
        {
            $bin = sprintf( "%05d", decbin($i));
            yield ['RadioTest', $bin, $timeStrings[0]];
        }
        // Try all timings
        foreach($timeStrings as $timeString)
        {
            yield ['TimeInterval', '11111', $timeString];
        }
    }*/

    /*public function getTimes () : array
    {
        $res = [[
            "2020-01-01 09:00:00 Europe/Amsterdam",
            "2021-01-01 09:00:00 Europe/Amsterdam",
            "2022-01-01 09:00:00 Europe/Amsterdam",
            "2023-01-01 09:00:00 Europe/Amsterdam",
            "2024-01-01 09:00:00 Europe/Amsterdam",
            "2025-01-01 09:00:00 Europe/Amsterdam",
        ]];
        // Check times in the past and future
        foreach([-1,1] as $Yi)
        {
            $row = [];
            foreach(['01','05','09','13','17','21'] as $H)
            {
                $row[] = sprintf('%s-06-01 %s:00:00 Europe/Amsterdam',
                                 date('Y') - $Yi, $H);
            }
            $res[] = $row;
        }
        for($i=-6; $i<2; $i++){
            $row = [];
            for($j=0; $j<6; $j++){
                $row[] = sprintf('%s-06-01 01:00:00 Europe/Amsterdam',
                                 date('Y') + $i + $j );
            }
            $res[] = $row;
        }
        $row = ['-10:15:30.333333'];
        $row[] = sprintf('%s-06-01 01:00:00 Europe/Amsterdam',
                         date('Y') );
        $res[] = array_merge($row, ['+1:00','+13:00:30','+14:00','+80:00']);
        return $res;
    }*/

    /**
     * Data provider used to test if we can add contests
     * - the to be created contest
     * - radio button boolean values (Bit string)
     */
    /*public function provideAddContest () : \Generator
    {
        //* - The different times of the contest
        //TODO: Try things like: "admin'--"
        $timeStrings = $this->getTimes();

        $contests  = [
            'NewContest',
            '⛀⛁⛂⛃⛄⛅⛆⛇⛈⛉⛊⛋⛌⛍⛎⛏',
            'name with spaces',
            str_repeat("A", 255)
        ];

        foreach ($contests as $contest) {
            yield [$contest,'11111', $timeStrings[0]];
        }
        // Try all possible values for the radio buttons
        // TODO: In the future test if the config option also works
        for($i=0; $i<2**5; $i++)
        {
            $bin = sprintf( "%05d", decbin($i));
            yield ['RadioTest', $bin, $timeStrings[0]];
        }
        // Try all timings
        foreach($timeStrings as $timeString)
        {
            yield ['TimeInterval', '11111', $timeString];
        }
    }

    protected static $formValues;
    //= array('contest[shortname]' => 'yes');
    /*    'contest[shortname]'            => hash('md5',$contest),
        'contest[name]'                 => $contest,
        'contest[activatetimeString]'   => $timeString[0],
        'contest[starttimeString]'      => $timeString[1],
        'contest[freezetimeString]'     => $timeString[2],
        'contest[endtimeString]'        => $timeString[3],
        'contest[unfreezetimeString]'   => $timeString[4],
        'contest[deactivatetimeString]' => $timeString[5],
        'contest[starttimeEnabled]'     => $radioValues[0],
        'contest[processBalloons]'      => $radioValues[1],
        'contest[public]'               => $radioValues[2],
        'contest[openToAllTeams]'       => $radioValues[3],
        'contest[enabled]'              => $radioValues[4],
    );*/
    //protected static $baseUrlLong = 'http://localhost/jury/contests';

    /**
     * Test that the role can add a new contest
     * @var string contest The contest to add
     * @var string radioValues The Radio settings of the contest
     * @var array timeString The time for a certain field as string
     * @dataProvider provideAddContest
     */
     /*public function CheckAddContest (
        string $contest,
        string $radioValues,
        array $timeString) : void
    {
        $this->logIn();
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        $link = $this->verifyLink(static::$addButton, static::$baseUrlLong.'/add');
        $crawler = $this->client->click($link);

        $h1s = $crawler->filter('h1')->extract(array('_text'));
	    $this->assertEquals('Add contest', $h1s[0]);

        $this->client->submitForm('Save', [
            'contest[shortname]'            => hash('md5',$contest),
            'contest[name]'                 => $contest,
            'contest[activatetimeString]'   => $timeString[0],
            'contest[starttimeString]'      => $timeString[1],
            'contest[freezetimeString]'     => $timeString[2],
            'contest[endtimeString]'        => $timeString[3],
            'contest[unfreezetimeString]'   => $timeString[4],
            'contest[deactivatetimeString]' => $timeString[5],
            'contest[starttimeEnabled]'     => $radioValues[0],
            'contest[processBalloons]'      => $radioValues[1],
            'contest[public]'               => $radioValues[2],
            'contest[openToAllTeams]'       => $radioValues[3],
            'contest[enabled]'              => $radioValues[4],
        ]);
        foreach(['jury','admin'] as $role)
        {
            $this->testContestsOverview($role, 200, $contest);
        }
    }*/

    /*


    /**
     * Remove the contest
     */
    /*public function CheckDeleteContest () : void
    {
        $contestShort = 'demo';
        $contestLong = 'Demo contest';
        $cid = '2';
        // At this moment of commiting this is the cid (2) of demo, this will fail if someone alters
        // this in the future.
        $this->verifyLink($contestShort, static::$baseUrlLong.'/'.$cid);

        $this->assertSelectorExists('a:contains('.$contestShort.')');
        $this->assertSelectorExists('a:contains('.$contestLong.')');
        // Now we follow the link and it should be deleted.
        // Modal approach
        // $this->assertSelectorExists('p:contains(Are you sure?)');
        $this->verifyPageResponse('GET', static::$baseUrl.'/'.$cid.'/delete', 200);
        //$crawler = $this->getCurrentCrawler();
        $this->client->submitForm('Delete', []);

        //    $link = $this->verifyLink(static::$addButton, static::$baseUrlLong.'/add');
        //$crawler = $this->client->click($link);

        $this->assertSelectorNotExists('a:contains('.$contestShort.')');
        $this->assertSelectorNotExists('a:contains('.$contestLong.')');
    }*/


}
