<?php declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Tests\Unit\BaseTestCase as BaseTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tester\Constraint\CommandIsSuccessful;

class ResetUserPasswordCommandTest extends BaseTestCase
{
    public static string $demoPassword = 'demo';
    public static string $commandName = 'domjudge:reset-user-password';
    protected Application $app;
    protected Command $command;
    protected CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setup();
        $this->app = new Application(self::$kernel);
        /** @var LazyCommand $command */
        $command = $this->app->find(static::$commandName);
        $this->command = $command->getCommand();
        $this->commandTester = new CommandTester($this->command);
    }

    public function apiRequest(string $password, int $statusCode, string $user = 'demo'): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        $server['PHP_AUTH_USER'] = $user;
        $server['PHP_AUTH_PW'] = $password;

        $this->client->request('GET', '/api/judgehosts', [], [], $server);
        $response = $this->client->getResponse();
        self::assertEquals($statusCode, $response->getStatusCode());
    }

    public function helperResetForUser(string $defaultPassword, string $user = 'demo'): string
    {
        $passwordPosition = 7;
        $this->roles = ['admin'];
        $this->setupUser();
        $this->apiRequest($defaultPassword, 200);

        $this->commandTester->execute(['username'=>$user]);

        $this->commandTester->assertCommandIsSuccessful();

        // the output of the command in the console
        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('[OK] New password for '.$user.' is', $output);
        $newPassword = explode(' ', $output)[$passwordPosition];
        $this->apiRequest($defaultPassword, 401);
        $this->apiRequest($newPassword, 200);
        return $newPassword;
    }

    public function testApiUser(): void
    {
        $newPassword = $this->helperResetForUser(self::$demoPassword);
        $this->helperResetForUser($newPassword);
    }

    public function testApiUserNoResetOtherUser(): void
    {
        $adminPasswordFile = sprintf(
            '%s/%s',
            static::getContainer()->getParameter('domjudge.etcdir'),
            'initial_admin_password.secret'
        );
        $adminPassword = trim(file_get_contents($adminPasswordFile));
        $this->apiRequest($adminPassword, 200, 'admin');
        $newDemoPassword = $this->helperResetForUser(self::$demoPassword);
        $this->apiRequest($adminPassword, 200, 'admin');
        $this->apiRequest($newDemoPassword, 401, 'admin');
    }

    public function testNonExistingUser(): void
    {
        $user = 'NotAnUser';
        $statusCode = $this->commandTester->execute(['username'=>$user]);
        self::assertThat(
            $statusCode,
            $this->logicalNot($this->equalTo(new CommandIsSuccessful())),
            'Command should fail with missing parameters.'
        );
        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('[ERROR] Can not find user with username '.$user, $output);
    }

    public function testMissingUserParameter(): void
    {
        $output = '';
        try {
            $this->commandTester->execute([]);
        } catch (RuntimeException $e) {
            $output = $e->getMessage();
        }
        static::assertStringContainsString('Not enough arguments (missing: "username").', $output);
    }

    public function testNonUsedParameter(): void
    {
        $output = '';
        $user = 'demo';
        try {
            $this->commandTester->execute(['Username'=>$user]);
        } catch (InvalidArgumentException $e) {
            $output = $e->getMessage();
        }
        static::assertStringContainsString('The "Username" argument does not exist.', $output);
    }

    public function testGetHelp(): void
    {
        $this->app->setAutoExit(false);
        $input = new ArrayInput(['command' => static::$commandName, '--help']);
        $outputBuffer = new BufferedOutput();
        $this->app->run($input, $outputBuffer);
        $output = $outputBuffer->fetch();
        $check = ['Description:','Reset the password of the given user',
                  'Usage:','domjudge:reset-user-password <username>',
                  'Arguments:','username','The username of the user to reset the password of',
                  'Options:',
                  'Display help for the given command. When no command is given display help for the list command'];
        foreach ($check as $message) {
            static::assertStringContainsString($message, $output);
        }
    }
}
