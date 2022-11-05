<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\DataFixtures\Test\EnableSelfregisterFixture;
use App\DataFixtures\Test\EnableSelfregisterSecondCategoryFixture;
use App\DataFixtures\Test\SampleSubmissionsInBucketsFixture;
use App\DataFixtures\Test\SelfRegisteredUserFixture;
use App\Entity\Contest;
use App\Entity\Submission;
use App\Entity\User;
use App\Tests\Unit\BaseTest;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Generator;

class PublicControllerTest extends BaseTest
{
    protected static string $formFieldName   = 'user_registration[';
    protected static string $urlRegister     = '/register';
    protected static string $urlUsers        = '/jury/users';
    protected static string $urlTeams        = '/jury/teams';
    protected static string $urlAffil        = '/jury/affiliations';
    protected static array  $requiredFields  = ['teamName','affiliationName','affiliationShortName','existingAffiliation'];
    protected static array  $formFields      = ['username','name','email','teamName','affiliation','affiliationName',
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
        $contest->setDeactivatetimeString((new \DateTime())->sub(new \DateInterval('PT1H'))->format(DateTimeInterface::ISO8601));
        $em->flush();

        $this->verifyPageResponse('GET', '/public', 200);
        self::assertSelectorExists('p.nodata:contains("No active contest")');
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
        string $category="",
        string $secondPassword="same"
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

    /**
     * @dataProvider selfRegisterProvider
     */
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

        /** @var User $user */
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

    /**
     * @dataProvider selfRegisterMissingFieldProvider
     */
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

    /**
     * @dataProvider selfRegisterDuplicateValueProvider
     */
    public function testSelfRegisterDuplicateValue(array $inputs, string $password, array $fixtures, string $category, string $error): void
    {
        $formFields = $this->setupSelfRegisterForm($inputs, $fixtures, $password, $category);
        self::assertSelectorNotExists('html:contains("'.$error.'")');
        $this->client->submitForm('Register', $formFields);
        $this->client->getCrawler()->html();
        self::assertSelectorExists('html:contains("'.$error.'")');
    }

    /**
     * @dataProvider selfRegisterNonExistingValuesProvider
     */
    public function testSelfRegisterNonExistingValues(array $inputs, array $fixtures, string $category): void
    {
        $tmpInputs = $inputs;
        $tmpInputs['existingAffiliation'] = '1';
        $formFields = $this->setupSelfRegisterForm($tmpInputs, $fixtures, 'pw', '2', 'pw');
        $selector = 'html:contains("This value is not valid.")';
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

    /**
     * @dataProvider selfRegisterWrongPasswordProvider
     */
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

    // username, name, email, teamName, affiliation, affiliationName
    // affiliationShortName, affiliationCountry, existingAffiliation
    // plainPassword
    public function selfRegisterProvider(): Generator
    {
        foreach ([[EnableSelfregisterFixture::class],[EnableSelfregisterFixture::class,EnableSelfregisterSecondCategoryFixture::class]] as $fixtures) {
            foreach (['2','4'] as $index => $category) {
                if (count($fixtures)===1 && $index!==1) {
                    continue;
                }
                yield[['username'=>'minimaluser', 'teamName'=>'NewTeam','affiliation'=>'none'],'shirt-recognize-bar-together', $fixtures, $category];
                yield[['username'=>'bruteforce', 'teamName'=>'Fib(4)','affiliation'=>'none'],'0112', $fixtures, $category];
                yield[['username'=>'fullUser', 'name'=>'Full User', 'email'=>'email@domain.com','teamName'=>'Trial','affiliation'=>'none'],'.', $fixtures, $category];
                yield[['username'=>'student@', 'teamName'=>'Student@Uni',
                       'affiliation'=>'new','affiliationName'=>'NewUni','affiliationShortName'=>'nu'],'p@ssword_Is_long', $fixtures, $category];
                yield[['username'=>'winner@', 'teamName'=>'FunnyTeamname',
                       'affiliation'=>'new','affiliationName'=>'SomeUni','affiliationShortName'=>'su','affiliationCountry'=>'SUR'],'p@ssword_Is_long', $fixtures, $category];
                yield[['username'=>'klasse', 'teamName'=>'Klasse', 'affiliation'=>'existing','existingAffiliation'=>'1'],'p@ssword_Is_long', $fixtures, $category];
                yield[['username'=>'newinstsamecountry', 'name'=>'CompetingDutchTeam', 'teamName'=>'SupperT3@m','affiliation'=>'new','affiliationName'=>'Vrije Universiteit',
                       'affiliationShortName'=>'vu','affiliationCountry'=>'NLD'],'demo', $fixtures, $category];
                if (count($fixtures)===1) {
                    yield[['username'=>'reusevaluesofexistinguser', 'name'=>'selfregistered user for example team','email'=>'electronic@mail.tld','teamName'=>'EasyEnough','affiliation'=>'none'],'demo', array_merge($fixtures, [SelfRegisteredUserFixture::class]),''];
                }
            }
        }
    }

    public function selfRegisterWrongPasswordProvider(): Generator
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

    public function selfRegisterDuplicateValueProvider(): Generator
    {
        $inputs = ['username'=>'originalUsername', 'teamName'=>'TeamName','affiliation'=>'none'];
        $password = 'foo';
        $fixtures = [EnableSelfregisterFixture::class, SelfRegisteredUserFixture::class];
        $category = '';
        foreach (static::$duplicateFields as $field => $value) {
            extract($value);
            $newInputs = $inputs;
            $newInputs[$field] = $input;
            if (strpos($field, 'affiliation') !== false) {
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

    public function selfRegisterNonExistingValuesProvider(): Generator
    {
        $fixtures = [EnableSelfregisterFixture::class,EnableSelfregisterSecondCategoryFixture::class];
        yield[['username'=>'nonexistingcategory', 'teamName'=>'NewTeam','affiliation'=>'none'], $fixtures, '42'];
        foreach ([[EnableSelfregisterFixture::class],$fixtures] as $newFixtures) {
            yield[['username'=>'nonexistingaffiliation', 'teamName'=>'NewTeam2','affiliation'=>'existing','existingAffiliation'=>'42'],$newFixtures, '2'];
        }
    }

    /**
     * Test that the problem statistics render the correct data
     *
     * @dataProvider provideTestProblemStatistics
     */
    public function testProblemStatistics(
        bool $removeFreezeTime,
        bool $removeUnfreezeTime,
        int $expectedGreenBoxes,
        int $expectedRedBoxes,
        int $expectedBlueBoxes
    ): void {
        $this->loadFixture(SampleSubmissionsInBucketsFixture::class);
        /** @var EntityManagerInterface $em */
        $em          = self::getContainer()->get(EntityManagerInterface::class);
        $demoContest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        if ($removeFreezeTime) {
            $demoContest->setFreezetimeString(null);
        }
        if ($removeUnfreezeTime) {
            $demoContest->setUnfreezetimeString(null);
        }
        $em->flush();

        // Get the problems page
        $this->verifyPageResponse('GET', '/public/problems', 200);

        $boxes            = $this->client->getCrawler()->filter('.problem-stats-item');
        $correctClasses   = array_map(fn(int $n) => 'problem-stats-item correct-' . $n, range(1, 9));
        $incorrectClasses = array_map(fn(int $n) => 'problem-stats-item incorrect-' . $n, range(1, 9));
        $frozenClasses    = array_map(fn(int $n) => 'problem-stats-item frozen-' . $n, range(1, 9));
        $correctBoxes     = $incorrectBoxes = $frozenBoxes = [];
        /** @var \DOMElement $box */
        foreach ($boxes as $box) {
            $class = $box->getAttribute('class');
            if (in_array($class, $correctClasses)) {
                $correctBoxes[] = $box;
            } elseif (in_array($class, $incorrectClasses)) {
                $incorrectBoxes[] = $box;
            } elseif (in_array($class, $frozenClasses)) {
                $frozenBoxes[] = $box;
            }
        }

        self::assertCount($expectedGreenBoxes, $correctBoxes);
        self::assertCount($expectedRedBoxes, $incorrectBoxes);
        self::assertCount($expectedBlueBoxes, $frozenBoxes);
    }

    public function provideTestProblemStatistics(): Generator
    {
        yield [false, false, 1, 1, 6]; // Keep both times, we expect one green, one red and six blue boxes (2 around the freeze and 4 at the end)
        yield [true, true, 3, 3, 0]; // Remove both times, we expect three green and three red boxes
        yield [false, true, 1, 1, 6]; // Remove the unfreeze time but not the freeze time, we expect one green, one red and six blue boxes
    }
}
