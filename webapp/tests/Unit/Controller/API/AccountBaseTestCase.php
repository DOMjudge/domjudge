<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use Generator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Yaml\Yaml;

abstract class AccountBaseTestCase extends BaseTestCase
{
    protected ?string $apiUser = 'admin';

    protected array $expectedAbsent = ['4242', 'nonexistent'];

    public function helperVerifyApiUsers(string $myURL, array $objectsBeforeTest, array $newUserPostData): void
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
        foreach ($newUserPostData as $key => $expectedValue) {
            if ($key !== 'password') {
                // For security we don't output the password in the API
                $newItemValue = $newItems[$listKey][$key];
                if ($key === 'roles' && in_array('admin', $newItemValue) && self::getContainer()->getParameter('kernel.debug')) {
                    $newItemValue = array_diff($newItemValue, ['team']);
                    // In development mode we add a team role to admin users for some API endpoints.
                };
                self::assertEquals($newItemValue, $expectedValue);
            }
        }
    }

    /**
     * @dataProvider provideNewAccount
     */
    public function testCreateUser(array $newUserPostData): void
    {
        $usersURL = $this->helperGetEndpointURL('users');
        $myURL = $this->helperGetEndpointURL($this->apiEndpoint);
        $objectsBeforeTest = $this->verifyApiJsonResponse('GET', $myURL, 200, $this->apiUser);
        $this->verifyApiJsonResponse('POST', $usersURL, 201, 'admin', $newUserPostData);
        $objectsAfterTest = $this->verifyApiJsonResponse('GET', $myURL, 200, $this->apiUser);
        $this->helperVerifyApiUsers($myURL, $objectsBeforeTest, $newUserPostData);
    }

    public function provideNewAccount(): Generator
    {
        yield [['username' => 'newUser-001',
                'name' => 'newUserWithName',
                'password' => 'xkcd-password-style-password',
                'roles' => ['team']]];
        yield [['username' => 'newAdmin',
                'name' => 'newUserWithName',
                'password' => 'xkcd-password-style-password',
                'roles' => ['admin']]];
    }

    /**
     * @dataProvider provideNewAccountFile
     */
    public function testCreateUserFileImport(string $newUsersFile, string $type, array $newUserPostData): void
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
        $this->helperVerifyApiUsers($myURL, $objectsBeforeTest, $newUserPostData);
    }

    public function provideNewAccountFile(): Generator
    {
        foreach ($this->provideNewAccount() as $index=>$testUser) {
            $testUser = $testUser[0];
            $user = $testUser['username'];
            $name = $testUser['name'];
            $pass = $testUser['password'];
            $role = $testUser['roles'][0];
            $tempData = ['id'=>$user, 'username'=> $user, 'name'=>$name, 'password'=>$pass, 'type'=>$role];
            // Handle TSV file
            if (count($testUser['roles']) !== 1) {
                static::markTestFailed("TSV can not have more than 1 role.");
            }
            $fileVersions = ['accounts'];
            if ($index === 0) {
                $fileVersions[] = 'File_Version';
            }
            foreach ($fileVersions as $fileVersion) {
                $file = $fileVersion."\t1";
                $file .= "\n$role\t$name\t$user\t$pass";
                yield [$file, 'tsv', $testUser];
            }
            // Handle YAML file
            $file = <<<EOF
- id: $user
  username: $user
  name: $name
  password: $pass
  type: $role
EOF;
            yield [$file, 'yaml', $testUser];
            $file = <<<EOF
- "id": $user
  "username": $user
  "name": $name
  "password": $pass
  "type": $role
EOF;
            yield [$file, 'yaml', $testUser];
            yield [Yaml::dump([$tempData], 1), 'yaml', $testUser];
            yield [Yaml::dump([$tempData], 1, 3), 'yaml', $testUser];
            yield [Yaml::dump([$tempData], 2, 2), 'yaml', $testUser];
            // Handle JSON file
            $file = <<<EOF
[{  id: $user,
    username: $user,
    name: $name,
    password: $pass,
    type: $role
}]
EOF;
            yield [$file, 'json', $testUser];
            $file = "[{id: \t$user,\tusername: $user, name:     $name,password: $pass, type: $role}]";
            yield [$file, 'json', $testUser];
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
