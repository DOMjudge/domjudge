<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Entity\User;

class UserControllerTest extends JuryControllerTest
{
    protected static $baseUrl        = '/jury/users';
    protected static $exampleEntries = ['admin','judgehost','Administrator','team'];
    protected static $shortTag       = 'user';
    protected static $deleteEntities = ['username' => ['dummy']];
    protected static $getIDFunc      = 'getUserid';
    protected static $className      = User::class;
    protected static $DOM_elements   = ['h1' => ['Users']];
}
