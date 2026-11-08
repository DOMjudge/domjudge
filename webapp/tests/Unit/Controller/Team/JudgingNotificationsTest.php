<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Team;

use App\DataFixtures\Test\RecentlyJudgedTooLateSubmissionFixture;
use App\Entity\Contest;
use App\Tests\Unit\BaseTestCase;
use Doctrine\ORM\EntityManagerInterface;

class JudgingNotificationsTest extends BaseTestCase
{
    protected array $roles = ['team'];

    /**
     * @return array{inTime: int, tooLate: int}
     */
    private function countNotifiedSubmissions(): array
    {
        $this->client->request('GET', '/team/updates');
        static::assertSame(200, $this->client->getResponse()->getStatusCode());
        $notifications = json_decode($this->client->getResponse()->getContent(), true)['unread_submissions'];

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $endtime = (float)$em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo'])->getEndtime();
        $tooLate = count(array_filter($notifications, fn(array $n) => (float)$n['submittime'] >= $endtime));
        return ['inTime' => count($notifications) - $tooLate, 'tooLate' => $tooLate];
    }

    public function testTooLateResultIsNotNotified(): void
    {
        $this->loadFixture(RecentlyJudgedTooLateSubmissionFixture::class);
        $this->withChangedConfiguration('show_too_late_result', false, function (): void {
            static::assertSame(['inTime' => 1, 'tooLate' => 0], $this->countNotifiedSubmissions());
        });
    }

    public function testTooLateResultIsNotifiedWhenShown(): void
    {
        $this->loadFixture(RecentlyJudgedTooLateSubmissionFixture::class);
        $this->withChangedConfiguration('show_too_late_result', true, function (): void {
            static::assertSame(['inTime' => 1, 'tooLate' => 1], $this->countNotifiedSubmissions());
        });
    }
}
