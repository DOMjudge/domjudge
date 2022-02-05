<?php declare(strict_types=1);

namespace App\DataFixtures\ExampleData;

use App\Entity\Clarification;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ClarificationFixture extends AbstractExampleDataFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $teamClarification = new Clarification();
        $teamClarification
            ->setContest($this->getReference(ContestFixture::DEMO_REFERENCE))
            ->setSubmittime(1518385638.901348000)
            ->setSender($this->getReference(TeamFixture::TEAM_REFERENCE))
            ->setJuryMember('admin')
            ->setProblem($this->getReference(ProblemFixture::HELLO_REFERENCE))
            ->setBody('Can you tell me how to solve this problem?')
            ->setAnswered(true);

        $juryResponse = new Clarification();
        $juryResponse
            ->setContest($this->getReference(ContestFixture::DEMO_REFERENCE))
            ->setInReplyTo($teamClarification)
            ->setSubmittime(1518385677.689197000)
            ->setRecipient($this->getReference(TeamFixture::TEAM_REFERENCE))
            ->setJuryMember('admin')
            ->setProblem($this->getReference(ProblemFixture::HELLO_REFERENCE))
            ->setBody("> Can you tell me how to solve this problem?\r\n\r\nNo, read the problem statement.")
            ->setAnswered(true);

        $manager->persist($teamClarification);
        $manager->persist($juryResponse);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [TeamFixture::class, ContestFixture::class, ProblemFixture::class];
    }
}
