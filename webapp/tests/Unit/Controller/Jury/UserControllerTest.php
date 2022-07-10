<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\User;

class UserControllerTest extends JuryControllerTest
{
    protected static string  $identifyingEditAttribute = 'username';
    protected static ?string $defaultEditEntityName    = 'judgehost';
    protected static array   $editEntitiesSkipFields   = ['username'];
    protected static array   $specialFieldOnlyUpdate   = ['plainPassword'];
    protected static string  $baseUrl                  = '/jury/users';
    protected static array   $exampleEntries           = ['admin', 'judgehost', 'Administrator', 'team'];
    protected static string  $shortTag                 = 'user';
    protected static array   $deleteEntities           = ['demo','judgehost'];
    protected static string  $deleteEntityIdentifier   = 'username';
    protected static string  $getIDFunc                = 'getUserid';
    protected static string  $className                = User::class;
    protected static array   $DOM_elements             = ['h1' => ['Users']];
    protected static string  $addForm                  = 'user[';
    protected static array   $addEntitiesShown         = ['name', 'username', 'email'];
    protected static array   $overviewNotShown         = ['plainPassword'];
    protected static array   $addEntities              = [['username'      => 'un',
                                                          'name'          => 'Alice',
                                                          'email'         => 'alice@example.org',
                                                          'plainPassword' => 'plainpassword',
                                                          'ipAddress'     => '10.0.0.0',
                                                          'enabled'       => '1',
                                                          'team'          => '0',
                                                          'user_roles'    => ['0' => '1', '1' =>'2']],
                                                         ['username' => 'ABCabc123_-@', 'name' => 'Allowed username'],
                                                         ['username' => 'nem', 'name' => 'No Email',
                                                          'email' => ''],
                                                         ['username' => 'npw', 'name' => 'No password',
                                                          'plainPassword' => ''],
                                                         ['username' => 'specialchar', 'name' => 'Special char in password',
                                                          'plainPassword' => '!@主裁判 судья !"#$%&()*+,-./:;<=>?@[\]^_`{|}~'],
                                                         ['username' => 'quoteinpassword', 'name' => 'quote_in_password',
                                                          'plainPassword' => "pass'w'ord"],
                                                         ['username' => 'nip', 'name' => 'No IP',
                                                          'ipAddress' => ''],
                                                         ['username' => 'ipv6-1', 'name' => 'IPv6-1',
                                                          'ipAddress' => '2001:db8::1234:5678:5.6.7.8'],
                                                         ['username' => 'ipv6-2', 'name' => 'IPv6-2',
                                                          'ipAddress' => '2001:db8:3333:4444:5555:6666:1.2.3.4'],
                                                         ['username' => 'ipv6-3', 'name' => 'IPv6-3',
                                                          'ipAddress' => '2001:0db8:0001:0000:0000:0ab9:C0A8:0102'],
                                                         ['username' => 'ipv6-4', 'name' => 'IPv6-4',
                                                          'ipAddress' => '2001:db8:3333:4444:5555:6666:7777:8888'],
                                                         ['username' => 'disabled', 'name' => 'Disabled',
                                                          'enabled' => '0'],
                                                         ['username' => 'teamless', 'name' => 'Teamless',
                                                          'team' => ''],
                                                         ['username' => 'singlerole-0', 'name' => 'Single Role-0',
                                                          'user_roles' => ['0' => '1']],
                                                         ['username' => 'singlerole-1', 'name' => 'Single Role-1',
                                                          'user_roles' => ['1' => '2']],
                                                         ['username' => 'singlerole-2', 'name' => 'Single Role-2',
                                                          'user_roles' => ['2' => '3']],
                                                         ['username' => 'singlerole-3', 'name' => 'Single Role-3',
                                                          'user_roles' => ['3' => '4']],
                                                         ['username' => 'singlerole-4', 'name' => 'Single Role-4',
                                                          'user_roles' => ['4' => '5']],
                                                         ['username' => 'singlerole-5', 'name' => 'Single Role-5',
                                                          'user_roles' => ['5' => '6']],
                                                         ['username' => 'singlerole-6', 'name' => 'Single Role-6',
                                                          'user_roles' => ['6' => '7']],
                                                         ['username' => 'singlerole-7', 'name' => 'Single Role-7',
                                                          'user_roles' => ['7' => '8']],
                                                         ['username' => 'singlerole-8', 'name' => 'Single Role-8',
                                                          'user_roles' => ['8' => '9']]];
}
