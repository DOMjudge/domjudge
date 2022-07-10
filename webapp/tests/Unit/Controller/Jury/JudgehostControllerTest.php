<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\ExtraJudgehostFixture;
use App\Entity\Judgehost;

class JudgehostControllerTest extends JuryControllerTest
{
    protected static string  $identifyingEditAttribute = 'hostname';
    protected static ?string $defaultEditEntityName    = 'example-judgehost1';
    protected static string  $baseUrl                  = '/jury/judgehosts';
    protected static array   $exampleEntries           = ['example-judgehost1'];
    protected static array   $deleteEntities           = ['example-judgehost1','example-judgehost2'];
    protected static string  $deleteEntityIdentifier   = 'hostname';
    protected static array   $deleteFixtures           = [ExtraJudgehostFixture::class];
    protected static string  $getIDFunc                = 'getJudgehostid';
    protected static string  $className                = Judgehost::class;
    protected static array   $DOM_elements             = ['h1' => ['Judgehosts']];
    protected static string  $add                      = '';
    protected static string  $edit                     = '';
}
