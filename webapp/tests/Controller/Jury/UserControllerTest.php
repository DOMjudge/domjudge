<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Entity\User;

class UserControllerTest extends JuryControllerTest
{
    protected static $baseUrl           = '/jury/users';
    protected static $deleteEntities    = array('username' => ['dummy']);
    protected static $getIDFunc         = 'getUserid';
    protected static $exampleEntries    = ['admin','judgehost','Administrator','team'];
    protected static $shortTag          = 'user';
    protected static $className         = User::class;
    protected static $DOM_elements      = array('h1' => ['Users']);
}
