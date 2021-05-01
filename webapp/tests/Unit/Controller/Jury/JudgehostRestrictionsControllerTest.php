<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\AddJudgehostRestrictionFixture;
use App\Entity\JudgehostRestriction;

class JudgehostRestrictionsControllerTest extends JuryControllerTest
{
    protected static $baseUrl        = '/jury/judgehost-restrictions';
    protected static $exampleEntries = ['No'];
    protected static $shortTag       = 'judgehost restriction';
    protected static $deleteEntities = ['name' => ['TestRestriction']];
    protected static $getIDFunc      = 'getRestrictionid';
    protected static $className      = JudgehostRestriction::class;
    protected static $DOM_elements   = ['h1' => ['Judgehost restrictions']];

    /**
     * @dataProvider provideDeleteEntity
     */
    public function testDeleteEntity(string $identifier, string $entityShortName): void
    {
        $this->loadFixture(AddJudgehostRestrictionFixture::class);
        parent::testDeleteEntity($identifier, $entityShortName);
    }
}
