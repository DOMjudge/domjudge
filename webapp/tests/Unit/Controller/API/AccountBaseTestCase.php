<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use Generator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Yaml\Yaml;

abstract class AccountBaseTestCase extends BaseTestCase
{
    protected ?string $apiUser = 'admin';

    protected array $expectedAbsent = ['4242', 'nonexistent'];

    public function helperVerifyApiUsers(string $myURL, array $objectsBeforeTest, array $newUserPostData, ?array $overwritten = null): void
    {
        $objectsAfterTest = $this->verifyApiJsonResponse('GET', $myURL, 200, $this->apiUser);
        $newItems = array_map(unserialize(...), array_diff(array_map(serialize(...), $objectsAfterTest), array_map(serialize(...), $objectsBeforeTest)));
        // Because we login again with the admin user we might see that one also
        if (count($newItems)===2) {
            self::assertContains('admin', array_column($newItems,'username'), "Found unexpected new/changed user");
            foreach ($newItems as $key=>$user) {
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
        $newUserPostData = array_merge($newUserPostData, (array)$overwritten);
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
    public function testCreateUser(array $newUserPostData, ?array $overwritten=null): void
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
    }

    public function provideNewAccount(): Generator
    {
        $defaultData = ['username' => 'newStaff', 'name' => 'newUserWithName', 'password' => 'xkcd-password-style-password'];
        $accountCombinations = [
            [['username' => 'newTeam-001', 'type' => 'team'], ['roles' => ['team']]],
            [['username' => 'newTeam-001', 'type' => 'admin'], ['roles' => ['admin']]],
            [['type' => 'admin'], ['roles' => ['admin']]],
            [['type' => 'judge'], ['roles' => ['jury']]],
            [['type' => 'jury'], ['type' => 'judge', 'roles' => ['jury']]],
            [['type' => 'api_writer'], ['type' => 'admin']],
            [['type' => 'api_reader'], ['type' => 'admin']],
            [['type' => 'api_source_reader'], ['type' => 'judge']],
        ];
        foreach ($accountCombinations as $combination) {
            $newUpload = array_merge($defaultData, $combination[0]);
            yield [$newUpload, $combination[1] ?? null];
        }
    }

    /**
     * @dataProvider provideNewAccountFile
     */
    public function testCreateUserFileImport(string $newUsersFile, string $type, array $newUserPostData, ?array $overwritten=null): void
    {
        $usersURL = $this->helperGetEndpointURL('users').'/accounts';
        $myURL = $this->helperGetEndpointURL($this->apiEndpoint);
        $objectsBeforeTest = $this->verifyApiJsonResponse('GET', $myURL, 200, $this->apiUser);
        $tempFile = tempnam(sys_get_temp_dir(), "/accounts-upload-test-");
        file_put_contents($tempFile, $newUsersFile);
        $tempUploadFile = new UploadedFile($tempFile, 'accounts.'.$type);
        
        $result = $this->verifyApiJsonResponse('POST', $usersURL, 200, 'admin', null, [$type => $tempUploadFile]);
        unlink($tempFile);

        self::assertEquals($result, "1 new account(s) successfully added.");
        $this->helperVerifyApiUsers($myURL, $objectsBeforeTest, $newUserPostData, $overwritten);
    }

    public function provideNewAccountFile(): Generator
    {
        foreach ($this->provideNewAccount() as $index=>$testUser) {
            if (isset($testUser['skipImportFile'])) {
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
            $tempData = ['id'=>$user, 'username'=> $user, 'name'=>$name, 'password'=>$pass, 'type'=>$role];
            // Handle TSV file
            $fileVersions = ['accounts'];
            if ($index === 0) {
                $fileVersions[] = 'File_Version';
            }
            foreach ($fileVersions as $fileVersion) {
                $file = $fileVersion."\t1";
                $file .= "\n$role\t$name\t$user\t$pass";
                yield [$file, 'tsv', $testUser, $overwritten];
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

    public function testListFilterTeam(): void
    {
        foreach (['9999','nan','nonexistent'] as $nonExpectedObjectId) {
            $url = $this->helperGetEndpointURL($this->apiEndpoint)."?team=".$nonExpectedObjectId;
            $objects = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);
            self::assertEquals([],$objects);
        }
        foreach ($this->expectedObjects as $expectedObject) {
            if (!isset($expectedObject['team_id'])) {
                continue;
            }
            $url = $this->helperGetEndpointURL($this->apiEndpoint)."?team=".$expectedObject['team_id'];
            $objects = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);
            $found = False;
            foreach ($objects as $possibleObject) {
                if ($possibleObject['username'] == $expectedObject['username']) {
                    $found = True;
                    foreach ($expectedObject as $key => $value) {
                        // Null values can also be absent.
                        static::assertEquals($value, $possibleObject[$key] ?? null, $key . ' has correct value.');
                    }
                }
            }
            self::assertEquals(True,$found);
        }
    }
}
