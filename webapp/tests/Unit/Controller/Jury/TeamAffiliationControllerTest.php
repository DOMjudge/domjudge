<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\SampleAffiliationsFixture;
use App\Entity\TeamAffiliation;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ConfigurationService;

class TeamAffiliationControllerTest extends JuryControllerTestCase
{
    protected static string  $baseUrl                  = '/jury/affiliations';
    protected static array   $exampleEntries           = ['UU', 'Utrecht University', 1];
    protected static string  $shortTag                 = 'affiliation';
    protected static array   $deleteEntities           = ['UU','FAU'];
    protected static string  $deleteEntityIdentifier   = 'shortname';
    protected static array   $deleteFixtures           = [SampleAffiliationsFixture::class];
    protected static string  $getIDFunc                = 'getExternalid';
    protected static string  $className                = TeamAffiliation::class;
    protected static array   $DOM_elements             = ['h1' => ['Affiliations']];
    protected static string  $identifyingEditAttribute = 'shortname';
    protected static ?string $defaultEditEntityName    = 'UU';
    protected static string  $addForm                  = 'team_affiliation[';
    protected static array   $addEntitiesShown         = ['shortname', 'name'];
    protected static array   $overviewSingleNotShown   = ['country'];
    protected static array   $addEntities              = [['shortname' => 'short',
                                                           'name' => 'New Affil',
                                                           'country' => 'NLD',
                                                           'internalcomments'=> 'Lorem ipsum dolor sit amet.',
                                                           'icpcid' => ''],
                                                          ['shortname' => 'cl',
                                                           'name' => 'Countryless',
                                                           'country' => ''],
                                                          ['shortname' => 'com',
                                                           'name' => 'No comment',
                                                           'internalcomments' => ''],
                                                          ['name' => 'icpc (string)',
                                                           'icpcid' => 'one'],
                                                          ['name' => 'icpc (number)',
                                                           'icpcid' => '15'],
                                                          ['name' => 'icpc (alpnum-_)',
                                                           'icpcid' => '-_1aZ'],
                                                          ['name' => 'Special chars 😀',
                                                           'shortname' => 'yes😀'],
                                                          ['name' => 'External set',
                                                           'externalid' => 'ext12-._']];
    protected static array   $addEntitiesFailure       = ['This value should not be blank.' => [['shortname' => ''],
                                                                                                ['name' => '']],
                                                          'Only letters, numbers, dashes and underscores are allowed.' => [['icpcid' => '()viol'],
                                                                                                                           ['icpcid' => '|viol']],
                                                          'Only letters, numbers, dashes, underscores and dots are allowed.' => [['externalid' => '()']]];

    public function testMultiDeleteTeamAffiliations(): void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Create some team affiliations to delete
        $affiliationsData = [
            ['name' => 'Affiliation 1 for multi-delete', 'shortname' => 'affil1md'],
            ['name' => 'Affiliation 2 for multi-delete', 'shortname' => 'affil2md'],
            ['name' => 'Affiliation 3 for multi-delete', 'shortname' => 'affil3md'],
        ];

        $affiliationIds = [];
        $createdAffiliations = [];

        foreach ($affiliationsData as $data) {
            $affiliation = new TeamAffiliation();
            $affiliation
                ->setName($data['name'])
                ->setShortname($data['shortname']);
            $em->persist($affiliation);
            $createdAffiliations[] = $affiliation;
        }

        $em->flush();

        // Get the IDs of the newly created affiliations
        foreach ($createdAffiliations as $affiliation) {
            $affiliationIds[] = $affiliation->getExternalid();
        }

        $affiliation1Id = $affiliationIds[0];
        $affiliation2Id = $affiliationIds[1];
        $affiliation3Id = $affiliationIds[2];

        // Verify affiliations exist before deletion
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        foreach ([1, 2, 3] as $i) {
            self::assertSelectorExists(sprintf('body:contains("Affiliation %d for multi-delete")', $i));
        }

        // Simulate multi-delete POST request
        $this->client->request(
            'POST',
            static::getContainer()->get('router')->generate('jury_team_affiliation_delete_multiple', ['ids' => [$affiliation1Id, $affiliation2Id]]),
            [
                'submit' => 'delete'
            ]
        );

        $this->checkStatusAndFollowRedirect();

        // Verify affiliations are deleted
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        self::assertSelectorNotExists('body:contains("Affiliation 1 for multi-delete")');
        self::assertSelectorNotExists('body:contains("Affiliation 2 for multi-delete")');
        // Affiliation 3 should still exist
        self::assertSelectorExists('body:contains("Affiliation 3 for multi-delete")');

        // Verify affiliation 3 can still be deleted individually
        $this->verifyPageResponse('GET', static::$baseUrl . '/' . $affiliation3Id . static::$delete, 200);
        $this->client->submitForm('Delete', []);
        $this->checkStatusAndFollowRedirect();
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
    }

    protected function helperProvideTranslateAddEntity(array $entity, array $expected): array
    {
        $config = static::getContainer()->get(ConfigurationService::class);
        $showFlags = $config->get('show_flags');
        // Remove setting country when we don't show it.
        if (!$showFlags) {
            unset($entity['country']);
            unset($expected['country']);
        }
        return [$entity, $expected];
    }
}
