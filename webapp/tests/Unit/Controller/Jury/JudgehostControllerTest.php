<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\Judgehost;

class JudgehostControllerTest extends JuryControllerTest
{
    protected static $identifyingEditAttribute = 'hostname';
    protected static $defaultEditEntityName    = 'example-judgehost1';
    protected static $baseUrl        = '/jury/judgehosts';
    protected static $exampleEntries = ['example-judgehost1'];
    protected static $deleteEntities = ['hostname' => ['example-judgehost1']];
    protected static $getIDFunc      = 'getJudgehostid';
    protected static $className      = Judgehost::class;
    protected static $DOM_elements   = ['h1' => ['Judgehosts']];
    protected static $add            = '';
}
