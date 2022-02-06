<?php declare(strict_types=1);

namespace App\DataFixtures\ExampleData;

use App\Entity\ContestProblem;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ContestProblemFixture extends AbstractExampleDataFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $helloPractice = new ContestProblem();
        $helloPractice
            ->setShortname('hello')
            ->setContest($this->getReference(ContestFixture::PRACTICE_REFERENCE))
            ->setProblem($this->getReference(ProblemFixture::HELLO_REFERENCE));

        $helloDemo = new ContestProblem();
        $helloDemo
            ->setShortname('hello')
            ->setContest($this->getReference(ContestFixture::DEMO_REFERENCE))
            ->setProblem($this->getReference(ProblemFixture::HELLO_REFERENCE))
            ->setColor('skyblue');

        $fltcmpDemo = new ContestProblem();
        $fltcmpDemo
            ->setShortname('fltcmp')
            ->setContest($this->getReference(ContestFixture::DEMO_REFERENCE))
            ->setProblem($this->getReference(ProblemFixture::FLTCMP_REFERENCE))
            ->setColor('indianred');

        $boolfindDemo = new ContestProblem();
        $boolfindDemo
            ->setShortname('boolfind')
            ->setContest($this->getReference(ContestFixture::DEMO_REFERENCE))
            ->setProblem($this->getReference(ProblemFixture::BOOLFIND_REFERENCE))
            ->setColor('green');

        $manager->persist($helloPractice);
        $manager->persist($helloDemo);
        $manager->persist($fltcmpDemo);
        $manager->persist($boolfindDemo);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [ContestFixture::class, ProblemFixture::class];
    }
}
