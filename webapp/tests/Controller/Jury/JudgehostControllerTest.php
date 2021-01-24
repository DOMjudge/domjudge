<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Entity\Judgehost;

class JudgehostControllerTest extends JuryControllerTest
{
    protected static $baseUrl           = '/jury/judgehosts';
    protected static $exampleEntries    = ['example-judgehost1'];
    protected static $getIDFunc         = 'getHostname';
    protected static $deleteEntities    = array('hostname' => ['example-judgehost1']);
    protected static $add               = '';

    protected static $DOM_elements      = array('h1' => ['Judgehosts']);
    protected static $className         = Judgehost::class;
}
