<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Entity\Contest;

class ContestControllerTest extends JuryControllerTest
{
    protected static $baseUrl         = '/jury/contests';
    protected static $shortTag        = 'contest';
    protected static $deleteEntities  = array('name' => ['Demo practice session']);
    protected static $getIDFunc       = 'getCid';
    protected static $exampleEntries  = ['Demo contest','Demo practice session'];

    protected static $DOM_elements    = array('h1' => ['Contests'],
                                              'h3' => array('admin' => ['Current contests', 'All available contests'],
                                                            'jury' => []));
    protected static $className       = Contest::class;
}
