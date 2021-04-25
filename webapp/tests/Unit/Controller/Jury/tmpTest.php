webapp/tests/Unit/Controller/Jury/ContestControllerTest.php
#use App\Tests\BaseTest;
#
#class ContestControllerTest extends BaseTest
#{
#    protected static $roles       = ['admin'];
#    protected static $baseUrl     = '/jury/contests';
#    protected static $baseUrlLong = 'http://localhost/jury/contests';
#    protected static $addButton   = ' Add new contest';
#
#    /**
#     * Test that jury contest overview page exists
#     * @var string role The role of the user
#     * @var int statuscode The statuscode the current user should get
#     * @var string baseContest The basecontest we use throughout these tests
#     * @dataProvider provideBasePage
#     */
#    public function testContestsOverview (
#   string $role,
#   int $statuscode,
#   string $baseContest) : void
#    {
#       static::$roles = [$role];
#        $this->logIn();
#        $this->verifyPageResponse('GET', static::$baseUrl, $statuscode);
#        if ($statuscode===200) {
#            $crawler = $this->getCurrentCrawler();
#            $h1s = $crawler->filter('h1')->extract(array('_text'));
#                $h3s = $crawler->filter('h3')->extract(array('_text'));
#                $this->assertEquals('Contests', $h1s[0]);
#                $this->assertEquals('Current contests', $h3s[0]);
#                $this->assertEquals('All available contests', $h3s[1]);
#            $this->assertSelectorExists('div:contains('.$baseContest.')');
#        }
#    }
#
#    /**
#     * Data provider used to test if the starting pages are sane
#     * - the base role of the user
#     * - the expected HTTP statuscode
#     * - the pre-existing contest
#     */
#    public function provideBasePage () : \Generator
#    {
#        $all_roles = ['admin','jury'];
#        $dis_roles = ['team'];
#        $contests  = ['Demo contest','Demo practice session'];
#        foreach ($contests as $contest) {
#            foreach ($all_roles as $role) {
#                    yield [$role, 200, $contest];
#            }
P
