<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use App\DataFixtures\Test\EnableSelfregisterFixture;
use App\DataFixtures\Test\EnableSelfregisterSecondCategoryFixture;
use App\DataFixtures\Test\SampleSubmissionsInBucketsFixture;
use App\DataFixtures\Test\SampleSubmissionsThreeTriesCorrectFixture;
use App\DataFixtures\Test\SelfRegisteredUserFixture;
use App\Entity\Contest;
use App\Entity\Submission;
use App\Entity\User;
use App\Service\ScoreboardService;
use App\Tests\Unit\BaseTestCase;
use App\Utils\Utils;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Symfony\Component\DomCrawler\Crawler;

class PublicControllerTest extends BaseTestCase
{
    protected static string $formFieldName   = 'user_registration[';
    protected static string $urlRegister     = '/register';
    protected static string $urlUsers        = '/jury/users';
    protected static string $urlTeams        = '/jury/teams';
    protected static string $urlAffil        = '/jury/affiliations';
    protected static array  $requiredFields  = ['teamName','affiliationName','affiliationShortName','existingAffiliation'];
    protected static array  $formFields      = ['username','name','teamName','affiliation','affiliationName',
                                                'affiliationShortName','affiliationCountry','existingAffiliation'];
    protected static array  $duplicateFields = ['username'=>['input'=>'selfregister','error'=>'The username \'"selfregistered"\' is already in use.'],
                                                'teamName'=>['input'=>'Example teamname','error'=>'This team name is already in use.'],
                                                'affiliationName'=>['input'=>'Utrecht University','error'=>'This affiliation name is already in use.'],
                                                'affiliationShortName'=>['input'=>'UU','error'=>'This affiliation shortname is already in use.']];

    public function testScoreboardNoContests(): void
    {
        // Deactivate the demo contest
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var Contest $contest */
        $contest = $em->getRepository(Contest::class)->findOneBy(['externalid' => 'demo']);
        $contest->setDeactivatetimeString((new \DateTime())->sub(new \DateInterval('PT1H'))->format(DateTimeInterface::ATOM));
        $em->flush();

        $this->verifyPageResponse('GET', '/public', 200);
        self::assertSelectorExists('p.nodata:contains("No active contest")');
    }

    public function testStaticScoreboardOnlyUsesRelativeLinks(): void
    {
        $this->loadFixture(SampleSubmissionsInBucketsFixture::class);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        static::getContainer()->get(ScoreboardService::class)->refreshCache($contest);

        $this->verifyPageResponse('GET', '/public?static=1', 200);
        $content = $this->client->getResponse()->getContent();

        self::assertDoesNotMatchRegularExpression('/(href|src)="\//', $content);
        self::assertStringContainsString('href="css/bootstrap.min.css', $content);
        self::assertStringContainsString('href="public/scoreboard-category-color.css"', $content);
        self::assertStringContainsString('data-submissions-url="public/submissions-data.json"', $content);
    }

    public function testScoreboardWarningMessage(): void
    {
        $this->verifyPageResponse('GET', '/public', 200);
        self::assertSelectorNotExists('div.alert-danger');

        $msg = 'This is a test contest';

        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var Contest $contest */
        $contest = $em->getRepository(Contest::class)->findOneBy(['externalid' => 'demo']);
        $contest->setWarningMessage($msg);
        $em->flush();

        $this->verifyPageResponse('GET', '/public', 200);
        self::assertSelectorTextContains('div.alert-danger', $msg);
    }

    public function testNoSelfRegister(): void
    {
        $this->verifyPageResponse('GET', static::$urlRegister, 403);
    }

    private function setupSelfRegisterForm(
        array $inputs,
        array $fixtures,
        string $password,
        string $category = "",
        string $secondPassword = "same"
    ): array {
        $this->loadFixtures($fixtures);
        $this->logOut();
        $this->verifyPageResponse('GET', static::$urlRegister, 200);
        self::assertSelectorExists('h1:contains("Register Account")');
        $formFields = [];
        foreach (static::$formFields as $field) {
            $key = static::$formFieldName.$field.']';
            if (array_key_exists($field, $inputs)) {
                $formFields[$key] = $inputs[$field];
            } else {
                $formFields[$key] = '';
            }
        }
        if (count($fixtures)!==1 && $category !== "") {
            $formFields[static::$formFieldName.'teamCategory]'] = $category;
        }
        $formFields[static::$formFieldName."plainPassword][first]"] = $password;
        if ($secondPassword === "same") {
            $formFields[static::$formFieldName."plainPassword][second]"] = $password;
        } else {
            $formFields[static::$formFieldName."plainPassword][second]"] = $secondPassword;
        }
        return $formFields;
    }

    #[DataProvider('selfRegisterProvider')]
    public function testSelfRegister(array $inputs, string $password, array $fixtures, string $category): void
    {
        $formFields = $this->setupSelfRegisterForm($inputs, $fixtures, $password, $category);
        $this->client->submitForm('Register', $formFields);
        // We expect the registration to work so an admin should see the values.
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();
        $this->verifyPageResponse('GET', static::$urlUsers, 200);
        foreach (['username','teamName'] as $field) {
            self::assertSelectorExists('html:contains("'.$inputs[$field].'")');
        }
        $this->verifyPageResponse('GET', static::$urlTeams, 200);
        self::assertSelectorExists('html:contains("'.$inputs['teamName'].'")');
        foreach (['affiliationName','affiliationShortName'] as $field) {
            if (array_key_exists($field, $inputs)) {
                $this->verifyPageResponse('GET', static::$urlAffil, 200);
                self::assertSelectorExists('html:contains("'.$inputs[$field].'")');
            }
        }

        /** @var User|null $user */
        $user = static::getContainer()
            ->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->findOneBy(['username' => $inputs['username']]);

        self::assertNotNull($user);
        self::assertNotEmpty($user->getExternalid());
        self::assertNotEmpty($user->getTeam()->getExternalid());
        if ($inputs['affiliation'] !== 'none') {
            self::assertNotEmpty($user->getTeam()->getAffiliation()->getExternalid());
        }
    }

    #[DataProvider('selfRegisterMissingFieldProvider')]
    public function testSelfRegisterMissingField(array $inputs, string $password, array $fixtures, string $category, string $rField): void
    {
        $formFields = $this->setupSelfRegisterForm($inputs, $fixtures, $password, $category);
        self::assertSelectorNotExists('html:contains("This value should not be blank.")');
        if (array_key_exists($rField, $inputs)) {
            $mutatedFormFields = $formFields;
            $mutatedFormFields[static::$formFieldName.$rField."]"] = '';
            $this->client->submitForm('Register', $mutatedFormFields);
            self::assertSelectorExists('html:contains("This value should not be blank.")');
        }
    }

    #[DataProvider('selfRegisterDuplicateValueProvider')]
    public function testSelfRegisterDuplicateValue(array $inputs, string $password, array $fixtures, string $category, string $error): void
    {
        $formFields = $this->setupSelfRegisterForm($inputs, $fixtures, $password, $category);
        self::assertSelectorNotExists('html:contains("'.$error.'")');
        $this->client->submitForm('Register', $formFields);
        $this->client->getCrawler()->html();
        self::assertSelectorExists('html:contains("'.$error.'")');
    }

    #[DataProvider('selfRegisterNonExistingValuesProvider')]
    public function testSelfRegisterNonExistingValues(array $inputs, array $fixtures, string $category): void
    {
        $tmpInputs = $inputs;
        $tmpInputs['existingAffiliation'] = '1';
        $formFields = $this->setupSelfRegisterForm($tmpInputs, $fixtures, 'pw', '2', 'pw');
        $selector = 'html:contains("The selected choice is invalid.")';
        self::assertSelectorNotExists($selector);
        $button = $this->client->getCrawler()->selectButton('Register');
        $form = $button->form($formFields, 'POST');
        $rawValues = $form->getPhpValues();
        $rawValues["user_registration"]['teamCategory'] = $category;
        if ($inputs['affiliation'] === 'existing') {
            $rawValues["user_registration"]['existingAffiliation'] = $inputs['existingAffiliation'];
        }
        $response = $this->client->request($form->getMethod(), $form->getUri(), $rawValues, $form->getPhpFiles());
        self::assertSelectorExists($selector);
    }

    #[DataProvider('selfRegisterWrongPasswordProvider')]
    public function testSelfRegisterWrongPassword(
        array $inputs,
        string $password,
        array $fixtures,
        string $category,
        string $secondPassword
    ): void {
        $formFields = $this->setupSelfRegisterForm($inputs, $fixtures, $password, $category, $secondPassword);
        $selector = 'html:contains("The password fields must match.")';
        self::assertSelectorNotExists($selector);
        $this->client->submitForm('Register', $formFields);
        self::assertSelectorExists($selector);
    }

    // username, name, teamName, affiliation, affiliationName
    // affiliationShortName, affiliationCountry, existingAffiliation
    // plainPassword
    public static function selfRegisterProvider(): Generator
    {
        foreach ([[EnableSelfregisterFixture::class],[EnableSelfregisterFixture::class,EnableSelfregisterSecondCategoryFixture::class]] as $fixtures) {
            foreach (['2','4'] as $index => $category) {
                if (count($fixtures)===1 && $index!==1) {
                    continue;
                }
                yield[['username'=>'minimaluser', 'teamName'=>'NewTeam','affiliation'=>'none'],'shirt-recognize-bar-together', $fixtures, $category];
                yield[['username'=>'bruteforce', 'teamName'=>'Fib(9)','affiliation'=>'none'],'01123581321', $fixtures, $category];
                yield[['username'=>'fullUser', 'name'=>'Full User', 'teamName'=>'Trial','affiliation'=>'none'],'..........', $fixtures, $category];
                yield[['username'=>'student@', 'teamName'=>'Student@Uni',
                       'affiliation'=>'new','affiliationName'=>'NewUni','affiliationShortName'=>'nu'],'p@ssword_Is_long', $fixtures, $category];
                yield[['username'=>'winner@', 'teamName'=>'FunnyTeamname',
                       'affiliation'=>'new','affiliationName'=>'SomeUni','affiliationShortName'=>'su','affiliationCountry'=>'SUR'],'p@ssword_Is_long', $fixtures, $category];
                yield[['username'=>'klasse', 'teamName'=>'Klasse', 'affiliation'=>'existing','existingAffiliation'=>'1'],'p@ssword_Is_long', $fixtures, $category];
                yield[['username'=>'newinstsamecountry', 'name'=>'CompetingDutchTeam', 'teamName'=>'SupperT3@m','affiliation'=>'new','affiliationName'=>'Vrije Universiteit',
                       'affiliationShortName'=>'vu','affiliationCountry'=>'NLD'],'demodemodemo', $fixtures, $category];
                if (count($fixtures)===1) {
                    yield[['username'=>'reusevaluesofexistinguser', 'name'=>'selfregistered user for example team','teamName'=>'EasyEnough','affiliation'=>'none'],'demodemodemo', [...$fixtures, SelfRegisteredUserFixture::class],''];
                }
            }
        }
    }

    public static function selfRegisterWrongPasswordProvider(): Generator
    {
        foreach ([[EnableSelfregisterFixture::class],[EnableSelfregisterFixture::class,EnableSelfregisterSecondCategoryFixture::class]] as $fixtures) {
            foreach (['2','4'] as $index => $category) {
                if ($index!==1) {
                    continue;
                }
                yield[['username'=>'twodifferentvalues', 'teamName'=>'NewTeam','affiliation'=>'none'],'shirt-recognize-bar-together', $fixtures, $category, '0112'];
                yield[['username'=>'firstemptyvalue', 'teamName'=>'NewTeam','affiliation'=>'none'],'', $fixtures, $category, '0112'];
                yield[['username'=>'secondemptyvalue', 'teamName'=>'NewTeam','affiliation'=>'none'],'shirt-recognize-bar-together', $fixtures, $category, ''];
            }
        }
    }

    public static function selfRegisterDuplicateValueProvider(): Generator
    {
        $inputs = ['username'=>'originalUsername', 'teamName'=>'TeamName','affiliation'=>'none'];
        $password = 'foo';
        $fixtures = [EnableSelfregisterFixture::class, SelfRegisteredUserFixture::class];
        $category = '';
        foreach (static::$duplicateFields as $field => $value) {
            ['input' => $input, 'error' => $error] = $value;
            $newInputs = $inputs;
            $newInputs[$field] = $input;
            if (str_contains($field, 'affiliation')) {
                $newInputs['affiliation'] = 'new';
                if ($field==='affiliationShortName') {
                    $newInputs['affiliationName'] = 'New Affiliation';
                } elseif ($field==='affiliationName') {
                    $newInputs['affiliationShortName'] = 'shortaffil';
                }
            }
            yield[$newInputs, $password, $fixtures, $category, $error];
        }
    }

    public function selfRegisterMissingFieldProvider(): Generator
    {
        foreach ($this->selfRegisterProvider() as $args) {
            foreach (static::$requiredFields as $field) {
                if ($args[3]==='4') {
                    continue; // Skip the 2nd category to not generate so many tests.
                }
                if (array_key_exists($field, $args[0])) {
                    yield array_merge($args, [$field]);
                }
            }
        }
    }

    public static function selfRegisterNonExistingValuesProvider(): Generator
    {
        $fixtures = [EnableSelfregisterFixture::class,EnableSelfregisterSecondCategoryFixture::class];
        yield[['username'=>'nonexistingcategory', 'teamName'=>'NewTeam','affiliation'=>'none'], $fixtures, '42'];
        foreach ([[EnableSelfregisterFixture::class],$fixtures] as $newFixtures) {
            yield[['username'=>'nonexistingaffiliation', 'teamName'=>'NewTeam2','affiliation'=>'existing','existingAffiliation'=>'42'],$newFixtures, '2'];
        }
    }

    /**
     * Test that the problem statistics render the correct data
     */
    #[DataProvider('provideTestProblemStatistics')]
    public function testProblemStatistics(
        bool $removeFreezeTime,
        bool $removeUnfreezeTime,
        int $expectedGreenBoxes,
        int $expectedRedBoxes,
        int $expectedBlueBoxes,
        ?string $freezeTime = null,
        bool $submitCorrectAfterEnd = false,
        bool $ignoreCorrectSubmission = false
    ): void {
        $this->loadFixture(SampleSubmissionsInBucketsFixture::class);
        /** @var EntityManagerInterface $em */
        $em          = self::getContainer()->get(EntityManagerInterface::class);
        $demoContest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        if ($removeFreezeTime) {
            $demoContest->setFreezetimeString(null);
        }
        if ($freezeTime !== null) {
            $demoContest->setFreezetimeString($freezeTime);
        }
        // Some data sets change the correct submission at 4:40.
        $submission = $em->getRepository(Submission::class)->findOneBy(
            ['submittime' => Utils::toEpochFloat('2022-01-01 04:40:00 Europe/Amsterdam')]
        );
        if ($submitCorrectAfterEnd) {
            // After the contest end, where it is too late.
            $submission->setSubmittime(Utils::toEpochFloat('2022-01-01 05:00:30 Europe/Amsterdam'));
        }
        if ($ignoreCorrectSubmission) {
            $submission->setValid(false);
        }
        if ($removeUnfreezeTime) {
            $demoContest->setUnfreezetimeString(null);
        }
        $em->flush();

        // Get the problems page
        $this->verifyPageResponse('GET', '/public/problems', 200);

        $boxes            = $this->client->getCrawler()->filter('.problem-stats-item');
        $correctClasses   = array_map(fn(int $n) => 'correct-' . $n, range(1, 9));
        $incorrectClasses = array_map(fn(int $n) => 'incorrect-' . $n, range(1, 9));
        $frozenClasses    = array_map(fn(int $n) => 'frozen-' . $n, range(1, 9));
        $correctBoxes     = $incorrectBoxes = $frozenBoxes = [];
        /** @var \DOMElement $box */
        foreach ($boxes as $box) {
            $classes = explode(' ', $box->getAttribute('class'));
            if (array_intersect($classes, $correctClasses)) {
                $correctBoxes[] = $box;
            } elseif (array_intersect($classes, $incorrectClasses)) {
                $incorrectBoxes[] = $box;
            } elseif (array_intersect($classes, $frozenClasses)) {
                $frozenBoxes[] = $box;
            }
        }

        self::assertCount(0, $this->client->getCrawler()->filter('.problem-stats-own'));
        self::assertCount($expectedGreenBoxes, $correctBoxes);
        self::assertCount($expectedRedBoxes, $incorrectBoxes);
        self::assertCount($expectedBlueBoxes, $frozenBoxes);
        // Whenever there is a freeze, it starts in bin 16 (4:00-4:15), so the last four bins
        // of each problem are frozen, also the ones without submissions.
        self::assertCount(
            $expectedBlueBoxes > 0 ? 4 * $demoContest->getProblems()->count() : 0,
            $this->client->getCrawler()->filter('.problem-stats-item[class*="frozen-"]')
        );
        // The freeze marker on the time axis is only shown while there are frozen bins,
        // under the first frozen bin. That is bin 16 (4:00-4:15), the first column of the axis being bin 0.
        $freezeMarkers = $this->client->getCrawler()->filter('.problem-stats-freeze')->each(
            fn(Crawler $marker) => $marker->attr('style')
        );
        self::assertSame($expectedBlueBoxes > 0, count($freezeMarkers) > 0);
        foreach ($freezeMarkers as $style) {
            self::assertSame('grid-column: 17', $style);
        }
    }

    public static function provideTestProblemStatistics(): Generator
    {
        // Frozen bins are a single box spanning both rows.
        yield [false, false, 1, 1, 3]; // Keep both times, we expect one green, one red and three blue boxes (1 around the freeze and 2 at the end)
        yield [true, true, 3, 3, 0]; // Remove both times, we expect three green and three red boxes
        yield [false, true, 1, 1, 3]; // Remove the unfreeze time but not the freeze time, we expect one green, one red and three blue boxes
        // Freeze exactly at the start of bin 16, so bin 15 (3:45-4:00) ends at the freeze and should not be frozen.
        yield [false, false, 1, 1, 3, '2022-01-01 04:00:00 Europe/Amsterdam'];
        // A correct submission after the contest end is too late and should not be shown.
        yield [true, true, 2, 3, 0, null, true];
        // An ignored submission should not be shown either.
        yield [true, true, 2, 3, 0, null, false, true];
    }

    /**
     * Test that the submissions popup only shows the simplified verdict of submissions.
     */
    public function testSubmissionsDataShowsSimplifiedVerdicts(): void
    {
        $this->loadFixture(SampleSubmissionsThreeTriesCorrectFixture::class);
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo'])->setFreezetimeString(null);
        // Its submissions are judged timelimit, wrong-answer and correct; make the first one a compiler error.
        $submissionId = $this->resolveReference(SampleSubmissionsThreeTriesCorrectFixture::class . ':0', Submission::class);
        $em->getRepository(Submission::class)->find($submissionId)->getJudgings()->first()->setResult('compiler-error');
        $em->flush();

        $this->client->request('GET', '/public/submissions-data/team/exteam/problem/hello.json');
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $verdicts = array_map(
            fn(array $submission) => strip_tags($submission['verdict']),
            $data['submissions']['team-exteam']['problem-hello']
        );
        // Newest first.
        self::assertSame(['correct', 'rejected', 'compiler-error'], $verdicts);
    }
}
