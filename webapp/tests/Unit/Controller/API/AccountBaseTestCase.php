<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\TeamWithExternalIdEqualsOneFixture;
use Generator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Yaml\Yaml;

abstract class AccountBaseTestCase extends BaseTestCase
{
    protected ?string $apiUser = 'admin';

    protected array $expectedAbsent = ['4242', 'nonexistent'];

    protected static array $defaultDataUserAdd = [
        'username' => 'newStaff', 'name' => 'newUserWithName', 'password' => 'xkcd-password-style-password'
    ];

    protected static array $accountAddCombinationsWithFile = [
        [['username' => 'newTeam-001', 'type' => 'team'], ['roles' => ['team']]],
        [['username' => 'newTeam-001', 'type' => 'admin'], ['roles' => ['admin']]],
        [['type' => 'admin'], ['roles' => ['admin']]],
        [['type' => 'judge'], ['roles' => ['jury']]],
        [['type' => 'jury'], ['type' => 'judge', 'roles' => ['jury']]],
        [['type' => 'api_writer'], ['type' => 'admin', 'roles' => ['api_writer']]],
        [['type' => 'api_reader'], ['type' => 'admin', 'roles' => ['api_reader']]],
        [['type' => 'api_source_reader'], ['type' => 'judge', 'roles' => ['api_source_reader']]],
        [['type' => 'balloon'], ['roles' => ['balloon'], 'type' => 'other']],
        [['type' => 'clarification_rw'], ['roles' => ['clarification_rw'], 'type' => 'other']],
        [['type' => 'cds'], ['roles' => ['api_source_reader', 'api_reader', 'api_writer'], 'type' => 'admin']],
        [['username' => 'cds', 'type' => 'admin'], ['roles' => ['api_source_reader', 'api_reader', 'api_writer'], 'type' => 'admin']],
        [['username' => 'cds', 'type' => 'jury'], ['roles' => ['api_source_reader', 'api_reader', 'api_writer'], 'type' => 'admin']],
    ];

    protected static array $accountAddCombinationsWithoutFile = [
        [['username' => 'cds', 'roles' => ['admin']], ['roles' => ['api_source_reader', 'api_reader', 'api_writer'], 'type' => 'admin']],
        [['username' => 'another_judgehost', 'roles' => ['judgehost']], ['type' => 'other']],
    ];

    protected static array $optionalAddKeys = ['id', 'name', 'password'];

    public function helperVerifyApiUsers(string $myURL, array $objectsBeforeTest, array $newUserPostData, ?array $overwritten = null): void
    {
        $objectsAfterTest = $this->verifyApiJsonResponse('GET', $myURL, 200, $this->apiUser);
        $newItems = array_map(unserialize(...), array_diff(array_map(serialize(...), $objectsAfterTest), array_map(serialize(...), $objectsBeforeTest)));
        // Because we login again with the admin user we might see that one also
        if (count($newItems) === 2) {
            self::assertContains('admin', array_column($newItems, 'username'), "Found unexpected new/changed user");
            foreach ($newItems as $key => $user) {
                // Ignore the admin user for the tests.
                if ($user['username'] === 'admin') {
                    unset($newItems[$key]);
                    break;
                }
            }
        } else {
            self::assertEquals(1, count($newItems));
        }
        $listKey = array_keys($newItems)[0];
        $newUserPostData = [...$newUserPostData, ...(array)$overwritten];
        foreach ($newUserPostData as $key => $expectedValue) {
            if ($key !== 'password') {
                // For security we don't output the password in the API
                $newItemValue = $newItems[$listKey][$key];
                if ($key === 'roles' &&
                    (in_array('admin', $newItemValue) || in_array('jury', $newItemValue)) &&
                    self::getContainer()->getParameter('kernel.debug')
                ) {
                    $newItemValue = array_diff($newItemValue, ['team']);
                    // In development mode we add a team role to admin users for some API endpoints.
                };
                if (is_array($newItemValue)) {
                    sort($newItemValue);
                }
                if (is_array($expectedValue)) {
                    sort($expectedValue);
                }
                self::assertEquals($expectedValue, $newItemValue);
            }
        }
    }

    /**
     * @dataProvider provideNewAccount
     */
    public function testCreateUser(array $newUserPostData, ?array $overwritten = null): void
    {
        // This is only relevant for another test
        if (isset($newUserPostData['skipImportFile'])) {
            unset($newUserPostData['skipImportFile']);
        }
        if (!isset($newUserPostData['roles'])) {
            $newUserPostData['roles'] = [$newUserPostData['type']];
        }
        $usersURL = $this->helperGetEndpointURL('users');
        $myURL = $this->helperGetEndpointURL($this->apiEndpoint);
        $objectsBeforeTest = $this->verifyApiJsonResponse('GET', $myURL, 200, $this->apiUser);
        $this->verifyApiJsonResponse('POST', $usersURL, 201, 'admin', $newUserPostData);
        $objectsAfterTest = $this->verifyApiJsonResponse('GET', $myURL, 200, $this->apiUser);
        $this->helperVerifyApiUsers($myURL, $objectsBeforeTest, $newUserPostData, $overwritten);
        $url = $this->helperGetEndpointURL('account');
        $this->verifyApiJsonResponse('GET', $url, 200, $newUserPostData['username'], null, [], $newUserPostData['password']);
    }

    public function provideNewAccount(): Generator
    {
        $defaultData = static::$defaultDataUserAdd;
        $accountCombinationsWithFile = static::$accountAddCombinationsWithFile;
        $accountCombinationsWithoutFile = static::$accountAddCombinationsWithoutFile;
        foreach (static::$accountAddCombinationsWithFile as $templateAccount) {
            $result = array_merge($templateAccount[1], $templateAccount[0]);
            $expectation = $templateAccount[1];
            if (array_key_exists('type', $templateAccount[0])) {
                unset($result['type']);
                if (!array_key_exists('type', $templateAccount[1])) {
                    // Sometimes we insert a non-CLICS type which is translated to another CLICS type.
                    $expectation['type'] = $templateAccount[0]['type'];
                }
            }
            $accountCombinationsWithoutFile[] = [$result, $expectation];
        }
        foreach ([static::$accountAddCombinationsWithFile, $accountCombinationsWithoutFile] as $ind => $accountCombinations) {
            if ($ind === 1) {
                $defaultData['skipImportFile'] = true;
            }
            foreach ($accountCombinations as $combination) {
                $newUpload = array_merge($defaultData, $combination[0]);
                yield [$newUpload, $combination[1]];
            }
        }
    }

    /**
     * @dataProvider provideNewAccountFile
     */
    public function testCreateUserFileImport(string $newUsersFile, string $type, array $newUserPostData, ?array $overwritten = null): void
    {
        $this->loadFixture(TeamWithExternalIdEqualsOneFixture::class);
        $usersURL = $this->helperGetEndpointURL('users').'/accounts';
        $myURL = $this->helperGetEndpointURL($this->apiEndpoint);
        $objectsBeforeTest = $this->verifyApiJsonResponse('GET', $myURL, 200, $this->apiUser);
        $tempFile = tempnam(sys_get_temp_dir(), "/accounts-upload-test-");
        file_put_contents($tempFile, $newUsersFile);
        $tempUploadFile = new UploadedFile($tempFile, 'accounts.'.$type);

        $result = $this->verifyApiJsonResponse('POST', $usersURL, 200, 'admin', null, [$type => $tempUploadFile]);

        self::assertEquals($result, "1 new account(s) successfully added.");
        $this->helperVerifyApiUsers($myURL, $objectsBeforeTest, $newUserPostData, $overwritten);
        $url = $this->helperGetEndpointURL('account');
        $this->verifyApiJsonResponse('GET', $url, 200, $newUserPostData['username'], null, [], $newUserPostData['password']);
        unlink($tempFile);
    }

    public function provideNewAccountFile(): Generator
    {
        foreach ($this->provideNewAccount() as $index => $testUser) {
            if (isset($testUser[0]['skipImportFile'])) {
                // Not all properties which we can set via the API account endpoint can also
                // be imported via the API file import.
                continue;
            }
            $overwritten = $testUser[1] ?? null;
            $testUser = $testUser[0];
            $user = $testUser['username'];
            $name = $testUser['name'];
            $pass = $testUser['password'];
            $role = $testUser['type'];
            $tempData = ['id' => $user,  'username' => $user, 'name' => $name, 'password' => $pass, 'type' => $role];
            // Handle TSV file
            $fileVersions = ['accounts'];
            if ($index === 0) {
                $fileVersions[] = 'File_Version';
            }
            foreach ($fileVersions as $fileVersion) {
                foreach (["\r\n", "\n"] as $lineEnding) {
                    foreach ([true, false] as $fileEnding) {
                        $file = $fileVersion . "\t1";
                        $file .= "{$lineEnding}{$role}\t$name\t$user\t$pass";
                        if ($fileEnding) {
                            $file .= "$lineEnding";
                        }
                        yield [$file, 'tsv', $testUser, $overwritten];
                    }
                }
            }
            // Handle YAML file
            $file = <<<EOF
- id: $user
  username: $user
  name: $name
  password: $pass
  type: $role
EOF;
            yield [$file, 'yaml', $testUser, $overwritten];
            $file = <<<EOF
- "id": $user
  "username": $user
  "name": $name
  "password": $pass
  "type": $role
EOF;
            yield [$file, 'yaml', $testUser, $overwritten];
            yield [Yaml::dump([$tempData], 1), 'yaml', $testUser, $overwritten];
            yield [Yaml::dump([$tempData], 1, 3), 'yaml', $testUser, $overwritten];
            yield [Yaml::dump([$tempData], 2, 2), 'yaml', $testUser, $overwritten];
            // Handle JSON file
            $file = <<<EOF
[{  id: $user,
    username: $user,
    name: $name,
    password: $pass,
    type: $role,
}]
EOF;
            yield [$file, 'json', $testUser, $overwritten];
            $file = "[{id: \t$user,\tusername: $user, name:     $name,password: $pass, type: $role   }]";
            yield [$file, 'json', $testUser, $overwritten];
        }
    }

    public function testCreateUserNoPassword(): void
    {
        $newUserPostData = static::$defaultDataUserAdd;
        $newUserPostData['roles'] = ['team'];
        unset($newUserPostData['password']);
        $usersURL = $this->helperGetEndpointURL('users');
        $myURL = $this->helperGetEndpointURL($this->apiEndpoint);
        $objectsBeforeTest = $this->verifyApiJsonResponse('GET', $myURL, 200, $this->apiUser);
        $response = $this->verifyApiJsonResponse('POST', $usersURL, 201, 'admin', $newUserPostData);
        $this->helperVerifyApiUsers($myURL, $objectsBeforeTest, $newUserPostData);
        $url = $this->helperGetEndpointURL('account');
        $this->verifyApiJsonResponse('GET', $url, 401, $newUserPostData['username'], null, [], null);
        $this->verifyApiJsonResponse('GET', $url, 401, $newUserPostData['username'], null, [], '');
    }

    /**
     * @dataProvider provideNewAccountFileMissingField
     */
    public function testCreateUserFileImportMissingField(string $newUsersFile, string $type, array $newUserPostData, string $errorMessage, ?array $overwritten = null, int $statusCode = 400): void
    {
        $usersURL = $this->helperGetEndpointURL('users').'/accounts';
        $myURL = $this->helperGetEndpointURL($this->apiEndpoint);
        $objectsBeforeTest = $this->verifyApiJsonResponse('GET', $myURL, 200, $this->apiUser);
        $tempFile = tempnam(sys_get_temp_dir(), "/accounts-upload-test-");
        file_put_contents($tempFile, $newUsersFile);
        $tempUploadFile = new UploadedFile($tempFile, 'accounts.'.$type);

        $result = $this->verifyApiJsonResponse('POST', $usersURL, $statusCode, 'admin', null, [$type => $tempUploadFile]);

        $res = $result;
        if ($statusCode !== 200) {
            $res = $result['message'];
        }
        self::assertEquals($errorMessage, $res);
        unlink($tempFile);
    }

    public function provideNewAccountFileMissingField(): Generator
    {
        foreach ($this->provideNewAccount() as $index => $testUser) {
            if (isset($testUser[0]['skipImportFile'])) {
                // Not all properties which we can set via the API account endpoint can also
                // be imported via the API file import.
                continue;
            }
            $overwritten = $testUser[1] ?? null;
            $testUser = $testUser[0];
            $user = $testUser['username'];
            $name = $testUser['name'];
            $pass = $testUser['password'];
            $role = $testUser['type'];
            $tempData = ['id' => $user,  'username' => $user, 'name' => $name, 'password' => $pass, 'type' => $role];
            // Handle TSV file
            $fileVersions = ['accounts', 'File_Version'];
            foreach ($fileVersions as $fileVersion) {
                $file = $fileVersion."\t1";
                // The only field we can 'forget' is the password due to how the file is interpret.
                $file .= "\n$role\t$name\t$user\n";
                yield [$file, 'tsv', $testUser, 'Error while adding accounts: Not enough values on line 2', $overwritten];
            }
            foreach (array_keys($tempData) as $skipThisKey) {
                $newMissingData = $tempData;
                unset($newMissingData[$skipThisKey]);
                $statusCode = 400;
                $message = "Error while adding accounts: Missing key: '" . $skipThisKey . "' for block: 0.";
                if (in_array($skipThisKey, static::$optionalAddKeys)) {
                    $statusCode = 200;
                    $message = '1 new account(s) successfully added.';
                }
                yield [Yaml::dump([$newMissingData]), 'yaml', $testUser, $message, $overwritten, $statusCode];
                yield [json_encode([$newMissingData]), 'json', $testUser, $message, $overwritten, $statusCode];
            }
            /* We only return the data for 1 user,
               we run the loop to make sure 'skipImportFile' is not encountered on the first user. */
            break;
        }
    }

    public function testListFilterTeam(): void
    {
        foreach (['9999','nan','nonexistent'] as $nonExpectedObjectId) {
            $url = $this->helperGetEndpointURL($this->apiEndpoint)."?team=".$nonExpectedObjectId;
            $objects = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);
            self::assertEquals([], $objects);
        }
        foreach ($this->expectedObjects as $expectedObject) {
            if (!isset($expectedObject['team_id'])) {
                continue;
            }
            $url = $this->helperGetEndpointURL($this->apiEndpoint)."?team=".$expectedObject['team_id'];
            $objects = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);
            $found = false;
            foreach ($objects as $possibleObject) {
                if ($possibleObject['username'] == $expectedObject['username']) {
                    $found = true;
                    foreach ($expectedObject as $key => $value) {
                        // Null values can also be absent.
                        static::assertEquals($value, $possibleObject[$key] ?? null, $key . ' has correct value.');
                    }
                }
            }
            self::assertEquals(true, $found);
        }
    }

    /**
     * @dataProvider provideNewAccountFileNoPassword
     */
    public function testUserCreatedWithFileLogonNoPassword(string $newUsersFile, string $type): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), "/accounts-upload-test-");
        file_put_contents($tempFile, $newUsersFile);
        $tempUploadFile = new UploadedFile($tempFile, 'accounts.'.$type);
        $usersURL = $this->helperGetEndpointURL('users').'/accounts';
        $result = $this->verifyApiJsonResponse('POST', $usersURL, 200, 'admin', null, [$type => $tempUploadFile]);
        self::assertEquals($result, "1 new account(s) successfully added.");

        // The user has no password so should not be able to login.
        $url = $this->helperGetEndpointURL('account');
        $this->verifyApiJsonResponse('GET', $url, 401, 'userUploadedViaAPI', null, [], '');
        $this->verifyApiJsonResponse('GET', $url, 401, 'userUploadedViaAPI', null, [], null);
        unlink($tempFile);
    }

    public function provideNewAccountFileNoPassword(): Generator
    {
        // We don't properly handle the case where the password is not provided.
        // But we skip this test for TSV as its deprecated and it does not allow to provide the IP
        // which would be the alternative where not setting a password would make sense.
        // External authentication system usage is not considered.
        $userData = static::$defaultDataUserAdd;
        unset($userData['password']);
        $userData['type'] = 'admin';
        $userData['id'] = $userData['username'];
        yield [Yaml::dump([$userData], 1), 'yaml'];
        yield [json_encode([$userData], JSON_THROW_ON_ERROR), 'json'];
    }
}
