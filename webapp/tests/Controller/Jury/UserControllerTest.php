<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Entity\User;
use App\Tests\BaseTest;

class UserControllerTest extends BaseTest
{
    protected static $roles     = ['admin'];
    protected static $baseUrl   = '/jury/users';
    protected static $getIDFunc = 'getUserid';

    public function testDeleteEntity () : void
    {
        $this->logOut();
        $this->logIn();
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        // Find a CID we can delete
        $em = self::$container->get('doctrine')->getManager();
        $ent = $em->getRepository(User::class)->findOneBy(['username' => 'dummy']);
        self::assertSelectorExists('i[class*=fa-trash-alt]');
        self::assertSelectorExists('body:contains("dummy")');
        $this->verifyPageResponse('GET', static::$baseUrl.'/'.$ent->{static::$getIDFunc}().'/delete', 200);
        $this->client->submitForm('Delete', []);
        self::assertSelectorNotExists('body:contains("dummy")');
    }
}
