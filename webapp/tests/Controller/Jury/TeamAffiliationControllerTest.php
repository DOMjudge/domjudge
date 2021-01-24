<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Entity\TeamAffiliation;
use Generator;

class TeamAffiliationControllerTest extends JuryControllerTest
{
    protected static $baseUrl           = '/jury/affiliations';
    protected static $deleteEntities    = array('shortname' => ['UU']);
    protected static $getIDFunc         = 'getAffilid';
    protected static $exampleEntries    = ['UU','Utrecht University',1];
    protected static $shortTag          = 'affiliation';
    protected static $addFormName       = 'team_affiliation';

    protected static $DOM_elements      = array('h1' => ['Affiliations']);
    protected static $className         = TeamAffiliation::class;

    public function provideAddEntity () : Generator
    {
        foreach (static::$baseEntities as $name) {
            $attrs = [];
            foreach (['shortname','name','comments'] as $field)
            {
                $attrs[$this->getFormKey($field)] = $name;
            }
            yield [$attrs, $name];
        }
    }

}
