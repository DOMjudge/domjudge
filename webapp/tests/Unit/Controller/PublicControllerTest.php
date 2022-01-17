<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Entity\Contest;
use App\Service\DOMJudgeService;
use App\Tests\Unit\BaseTest;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;

class PublicControllerTest extends BaseTest
{
    public function testScoreboardNoContests()
    {
        // Deactivate the demo contest
        $em = static::$container->get(EntityManagerInterface::class);
        /** @var Contest $contest */
        $contest = $em->getRepository(Contest::class)->findOneBy(['externalid' => 'demo']);
        $contest->setDeactivatetimeString((new \DateTime())->sub(new \DateInterval('PT1H'))->format(DateTimeInterface::ISO8601));
        $em->flush();

        $this->verifyPageResponse('GET', '/public', 200);
        self::assertSelectorExists('p.nodata:contains("No active contest")');
    }
}
