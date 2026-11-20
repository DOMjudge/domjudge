<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Clarification;
use App\Entity\Contest;
use App\Entity\Team;
use App\Utils\Utils;
use Doctrine\Persistence\ObjectManager;

/**
 * A clarification request of the demo team with one reply that was sent already and
 * one that is dated in the future, as can be set through the API or by shadowing.
 */
class ClarificationWithFutureReplyFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $contest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $team = $manager->getRepository(Team::class)->findOneBy(['externalid' => 'exteam']);

        $request = (new Clarification())
            ->setContest($contest)
            ->setSender($team)
            ->setSubmittime(Utils::now() - 120)
            ->setCategory('general')
            ->setBody('Request from the team');
        $manager->persist($request);

        foreach (['Reply sent already' => -60, 'Reply from the future' => 3600] as $body => $offset) {
            $reply = (new Clarification())
                ->setContest($contest)
                ->setRecipient($team)
                ->setInReplyTo($request)
                ->setSubmittime(Utils::now() + $offset)
                ->setCategory('general')
                ->setBody($body);
            $request->addReply($reply);
            $manager->persist($reply);
        }
        $manager->flush();

        $this->addReference(sprintf('%s:%d', static::class, 0), $request);
    }
}
