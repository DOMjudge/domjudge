<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\ExtraJudgehostFixture;
use App\Entity\Judgehost;
use Doctrine\ORM\EntityManagerInterface;

class JudgehostControllerTest extends JuryControllerTestCase
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

    public function testTimelineIsCollapsedByDefault(): void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();

        $this->verifyPageResponse('GET', '/jury/judgehosts', 200);
        $crawler = $this->getCurrentCrawler();
        $details = $crawler->filter('details#judgehost-timeline-details');
        self::assertCount(1, $details);
        self::assertNull($details->attr('open'));
    }

    public function testTimelineDataEndpoint(): void
    {
        $this->roles = ['jury'];
        $this->logOut();
        $this->logIn();

        $this->client->request('GET', '/jury/judgehosts/timeline-data');
        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $payload = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('now', $payload);
        self::assertArrayHasKey('twenty_four_hours_ago', $payload);
        self::assertArrayHasKey('judgehosts', $payload);
        self::assertArrayHasKey('data', $payload);
        self::assertIsArray($payload['judgehosts']);
        self::assertIsArray($payload['data']);
    }

    public function testLastIpAddressDisplayed(): void
    {
        $this->roles = ['jury'];
        $this->logOut();
        $this->logIn();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $judgehost = $em->getRepository(Judgehost::class)->findOneBy(['hostname' => 'example-judgehost1']);
        self::assertInstanceOf(Judgehost::class, $judgehost);
        $judgehost->setLastIpAddress('::ffff:192.0.2.42');
        $em->flush();

        $this->verifyPageResponse('GET', '/jury/judgehosts', 200);
        self::assertSelectorTextContains('table', '::ffff:192.0.2.42');

        $this->verifyPageResponse('GET', sprintf('/jury/judgehosts/%d', $judgehost->getJudgehostid()), 200);
        self::assertSelectorTextContains('table', '::ffff:192.0.2.42');
    }
}
