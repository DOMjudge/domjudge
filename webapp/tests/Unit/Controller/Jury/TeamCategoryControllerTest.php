<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\TeamCategory;
use Doctrine\ORM\EntityManagerInterface;

class TeamCategoryControllerTest extends JuryControllerTestCase
{
    protected static string  $identifyingEditAttribute = 'name';
    protected static ?string $defaultEditEntityName    = 'System';
    protected static string  $baseUrl                  = '/jury/categories';
    protected static array   $exampleEntries           = ['Participants', 'Observers', 'System', 'yes', 'no'];
    protected static string  $shortTag                 = 'category';
    protected static array   $deleteEntities           = ['System','Observers'];
    protected static string  $deleteEntityIdentifier   = 'name';
    protected static string  $getIDFunc                = 'getExternalid';
    protected static string  $className                = TeamCategory::class;
    protected static array   $DOM_elements             = ['h1' => ['Categories']];
    protected static string  $addForm                  = 'team_category[';
    protected static array   $addEntitiesShown         = ['name', 'sortorder'];
    protected static array   $addEntities              = [['name'                    => 'New Category',
                                                           'types'                   => [TeamCategory::TYPE_SCORING, TeamCategory::TYPE_BACKGROUND],
                                                           'sortorder'               => '1',
                                                           'color'                   => '#123456',
                                                           'visible'                 => '1',
                                                           'allow_self_registration' => '0',
                                                           'icpcid'                  => ''],
                                                          ['name' => 'Secondary',
                                                           'sortorder' => '2'],
                                                          ['name' => 'NonNegative',
                                                           'sortorder' => '0'],
                                                          ['name' => 'Large',
                                                           'sortorder' => '128'],
                                                          ['name' => 'FutureColor',
                                                           'types' => [TeamCategory::TYPE_SCORING, TeamCategory::TYPE_BACKGROUND],
                                                           'color' => 'UnknownColor'],
                                                          ['name' => 'NameColor',
                                                           'types' => [TeamCategory::TYPE_SCORING, TeamCategory::TYPE_BACKGROUND],
                                                           'color' => 'yellow'],
                                                          ['name' => 'Invisible',
                                                           'visible' => '0'],
                                                          ['name' => 'SelfRegistered',
                                                           'allow_self_registration' => '1'],
                                                          ['name' => 'ICPCid known (string)',
                                                           'icpcid' => 'eleven'],
                                                          ['name' => 'ICPCid known (number)',
                                                           'icpcid' => '11'],
                                                          ['name' => 'ICPCid known (alphanum-_)',
                                                           'icpcid' => '_123ABC-abc'],
                                                          ['name' => 'External set',
                                                           'externalid' => 'ext10-._'],
                                                          ['name' => 'Name with 😁']];
    protected static array   $addEntitiesFailure       = ['Only non-negative sortorders are supported' => [['sortorder' => '-10']],
                                                          'Only letters, numbers, dashes and underscores are allowed.' => [['icpcid' => '|violation', 'name' => 'ICPCid violation-1'],
                                                                                                                           ['icpcid' => '()violation', 'name' => 'ICPCid violation-2']],
                                                          'Only letters, numbers, dashes, underscores and dots are allowed.' => [['externalid' => 'yes|']],
                                                          'This value should not be blank.' => [['name' => '']]];

    public function testMultiDeleteTeamCategories(): void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Create some team categories to delete
        $categoriesData = [
            ['name' => 'Category 1 for multi-delete'],
            ['name' => 'Category 2 for multi-delete'],
            ['name' => 'Category 3 for multi-delete'],
        ];

        $categoryIds = [];
        $createdCategories = [];

        foreach ($categoriesData as $data) {
            $category = new TeamCategory();
            $category->setName($data['name']);
            $em->persist($category);
            $createdCategories[] = $category;
        }

        $em->flush();

        // Get the IDs of the newly created categories
        foreach ($createdCategories as $category) {
            $categoryIds[] = $category->getExternalid();
        }

        $category1Id = $categoryIds[0];
        $category2Id = $categoryIds[1];
        $category3Id = $categoryIds[2];

        // Verify categories exist before deletion
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        foreach ([1, 2, 3] as $i) {
            self::assertSelectorExists(sprintf('body:contains("Category %d for multi-delete")', $i));
        }

        // Simulate multi-delete POST request
        $this->client->request(
            'POST',
            static::getContainer()->get('router')->generate('jury_team_category_delete_multiple', ['ids' => [$category1Id, $category2Id]]),
            [
                'submit' => 'delete'
            ]
        );

        $this->checkStatusAndFollowRedirect();

        // Verify categories are deleted
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        self::assertSelectorNotExists('body:contains("Category 1 for multi-delete")');
        self::assertSelectorNotExists('body:contains("Category 2 for multi-delete")');
        // Category 3 should still exist
        self::assertSelectorExists('body:contains("Category 3 for multi-delete")');

        // Verify category 3 can still be deleted individually
        $this->verifyPageResponse('GET', static::$baseUrl . '/' . $category3Id . static::$delete, 200);
        $this->client->submitForm('Delete', []);
        $this->checkStatusAndFollowRedirect();
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
    }

    protected function helperProvideTranslateAddEntity(array $entity, array $expected): array
    {
        return [$entity, $expected];
    }
}
