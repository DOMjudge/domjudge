<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\ClarificationFixture;
use App\Entity\Clarification;
use App\Entity\Contest;
use App\Tests\Unit\BaseTestCase;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;

class ClarificationControllerTest extends BaseTestCase
{
    protected array $roles = ['jury'];

    /**
     * Test that the jury clarifications page contains the correct information.
     */
    public function testClarificationRequestIndex(): void
    {
        $this->loadFixture(ClarificationFixture::class);
        $this->verifyPageResponse('GET', '/jury', 200);
        $link = $this->verifyLinkToURL('Clarifications',
                                       'http://localhost/jury/clarifications');
        $this->client->click($link);
        $crawler = $this->checkStatusAndFollowRedirect();

        $h2s = $crawler->filter('h2')->extract(['_text']);
        self::assertEquals('New requests', $h2s[0]);
        self::assertEquals('Handled requests', $h2s[1]);
        self::assertEquals('General clarifications', $h2s[2]);

        self::assertSelectorExists('html:contains("Is it necessary to read the problem statement carefully?")');
    }

    /**
     * Test that unanswered and answered clarifications are under the right header.
     */
    public function testClarificationRequestIndexNewAndOldUnderRightHeader(): void
    {
        $this->loadFixture(ClarificationFixture::class);

        $this->verifyPageResponse('GET', '/jury/contests/demo/clarifications', 200);
        $crawler = $this->getCurrentCrawler();

        self::assertSelectorTextContains('h2#newrequests ~ div.table-wrapper', 'Is it necessary to');
        self::assertSelectorTextContains('h2#oldrequests ~ div.table-wrapper', 'What is 2+2?');
    }
    /**
     * Test that general clarification is under general clarifications header.
     */
    public function testClarificationRequestIndexHasGeneralClarifications(): void
    {
        $this->loadFixture(ClarificationFixture::class);

        $this->verifyPageResponse('GET', '/jury/contests/demo/clarifications', 200);
        $crawler = $this->getCurrentCrawler();

        // General clarification to all.
        self::assertSelectorTextContains('h2#clarifications ~ div.table-wrapper', 'Lunch is served');
        // Jury initiated message to specific team.
        self::assertSelectorTextContains('h2#clarifications ~ div.table-wrapper', 'There was a mistake');
    }

    /**
     * Test that the jury can view a clarification.
     */
    public function testClarificationRequestView(): void
    {
        $this->loadFixture(ClarificationFixture::class);
        /** @var Clarification $clar */
        $clar = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Clarification::class)->findOneBy(['body' => 'What is 2+2?']);
        $this->verifyPageResponse('GET', '/jury/contests/demo/clarifications/' . $clar->getExternalid(), 200);

        /** @var Clarification[] $clarifications */
        $clarifications = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Clarification::class)->findAll();
        $clarificationText = $this->getCurrentCrawler()->filter('div.card-text')->extract(['_text']);
        self::assertEquals('What is 2+2?',
                           trim($clarificationText[0]));
        self::assertEquals("You have a fast calculator in front of you.",
                           trim($clarificationText[1]));

        $this->verifyLinkToURL('Example teamname',
                               'http://localhost/jury/teams/exteam');
    }

    /**
     * Test that the jury can send a clarification to everyone.
     */
    public function testClarificationRequestComposeForm(): void
    {
        $this->verifyPageResponse('GET', '/jury/contests/demo/clarifications', 200);
        $link = $this->verifyLinkToURL('Send clarification',
                                       'http://localhost/jury/contests/demo/clarifications/send');

        $crawler = $this->client->click($link);

        $h1s = $crawler->filter('h1')->extract(['_text']);
        self::assertEquals('Send Clarification', $h1s[0]);

        $options = $crawler->filter('option')->extract(['_text']);
        self::assertEquals('ALL', $options[1]);
        self::assertEquals('DOMjudge (domjudge)', $options[2]);
        self::assertEquals('Example teamname (exteam)', $options[3]);

        $labels = $crawler->filter('label')->extract(['_text']);
        self::assertEquals('Send to', $labels[0]);
        self::assertEquals('Subject', $labels[1]);
        self::assertEquals('Message', $labels[2]);

        $this->client->submitForm('Send', [
            'jury_clarification[recipient]' => '',
            'jury_clarification[subject]' => 'demo#tech',
            'jury_clarification[message]' => 'This is a clarification',
        ]);

        $this->checkStatusAndFollowRedirect();

        self::assertSelectorTextContains('div.col-sm strong', 'All');
        self::assertSelectorTextContains('span.clarification-subject',
                                         'Technical issue');
        self::assertSelectorTextContains('div.card-text',
                                         'This is a clarification');
    }

    /**
     * Test that a user with only the clarification_rw role sees the team
     * clarifications, and not just the general ones.
     */
    public function testClarificationHandlerSeesTeamClarifications(): void
    {
        $this->roles = ['clarification_rw'];
        $this->logOut();
        $this->logIn();
        $this->loadFixture(ClarificationFixture::class);

        $this->verifyPageResponse('GET', '/jury/contests/demo/clarifications', 200);
        self::assertSelectorTextContains('h2#newrequests ~ div.table-wrapper', 'Is it necessary to');
        self::assertSelectorTextContains('h2#oldrequests ~ div.table-wrapper', 'What is 2+2?');

        /** @var Clarification $clar */
        $clar = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Clarification::class)->findOneBy(['body' => 'What is 2+2?']);
        $this->verifyPageResponse('GET', '/jury/contests/demo/clarifications/' . $clar->getExternalid(), 200);
        self::assertSelectorTextContains('div.card-text', 'What is 2+2?');
    }

    /**
     * External clarification ids are only unique per contest, so a second
     * contest may reuse one. Looking one up must stay scoped to its contest.
     */
    public function testClarificationRequestViewWithExternalIdReusedByOtherContest(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $demo = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);

        // External ids are assigned from the internal id unless they are set
        // explicitly, which is what an external CCS or the API does.
        $em->persist((new Clarification())
            ->setExternalid('shared-id')
            ->setContest($demo)
            ->setSubmittime(Utils::now())
            ->setJuryMember('admin')
            ->setBody('This one belongs to the demo contest')
            ->setAnswered(true));

        $otherContest = (new Contest())
            ->setExternalid('other')
            ->setName('Other contest')
            ->setShortname('other')
            ->setStarttimeString('2021-07-17 16:09:00 Europe/Amsterdam')
            ->setEndtimeString('2021-07-17 16:11:00 Europe/Amsterdam');
        $em->persist($otherContest);
        $em->persist((new Clarification())
            ->setExternalid('shared-id')
            ->setContest($otherContest)
            ->setSubmittime(Utils::now())
            ->setJuryMember('admin')
            ->setBody('This one belongs to the other contest')
            ->setAnswered(true));
        $em->flush();

        $this->verifyPageResponse('GET', '/jury/contests/demo/clarifications/shared-id', 200);
        self::assertSelectorTextContains('div.card-text', 'This one belongs to the demo contest');
    }
}
