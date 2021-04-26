<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\Contest;

class ContestControllerTest extends JuryControllerTest
{
    protected static $baseUrl        = '/jury/contests';
    protected static $exampleEntries = ['Demo contest','Demo practice session'];
    protected static $shortTag       = 'contest';
    protected static $deleteEntities = ['name' => ['Demo practice session']];
    protected static $getIDFunc      = 'getCid';
    protected static $className      = Contest::class;
    protected static $DOM_elements   = ['h1' => ['Contests'],
                                        'h3' => ['admin' => ['Current contests', 'All available contests'],
                                                 'jury' => []],
                                        'a.btn[title="Import contest"]' => ['admin' => [" Import contest"],'jury'=>[]]];
    protected static $deleteExtra    = ['pageurl'   => '/jury/contests/2',
                                        'deleteurl' => '/jury/contests/2/problems/3/delete',
                                        'selector'  => 'Boolean switch search',
                                        'fixture'   => NULL];
}
