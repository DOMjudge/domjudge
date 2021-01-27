<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Entity\Judgehost;

class JudgehostControllerTest extends JuryControllerTest
{
    protected static $baseUrl        = '/jury/judgehosts';
    protected static $exampleEntries = ['example-judgehost1'];
    protected static $deleteEntities = ['hostname' => ['example-judgehost1']];
    protected static $getIDFunc      = 'getHostname';
    protected static $className      = Judgehost::class;
    protected static $DOM_elements   = ['h1' => ['Judgehosts']];
    protected static $add            = '';
}
