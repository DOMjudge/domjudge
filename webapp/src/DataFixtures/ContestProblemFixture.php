<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ContestProblem;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Class ContestProblemFixture
 * @package App\DataFixtures
 */
class ContestProblemFixture extends AbstractExampleDataFixture implements DependentFixtureInterface
{
    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager)
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
            ->setColor('magenta');

        $fltcmpDemo = new ContestProblem();
        $fltcmpDemo
            ->setShortname('fltcmp')
            ->setContest($this->getReference(ContestFixture::DEMO_REFERENCE))
            ->setProblem($this->getReference(ProblemFixture::FLTCMP_REFERENCE))
            ->setColor('yellow');

        $boolfindDemo = new ContestProblem();
        $boolfindDemo
            ->setShortname('boolfind')
            ->setContest($this->getReference(ContestFixture::DEMO_REFERENCE))
            ->setProblem($this->getReference(ProblemFixture::BOOLFIND_REFERENCE))
            ->setColor('limegreen');

        $manager->persist($helloPractice);
        $manager->persist($helloDemo);
        $manager->persist($fltcmpDemo);
        $manager->persist($boolfindDemo);
        $manager->flush();
    }

    /**
     * @inheritDoc
     */
    public function getDependencies()
    {
        return [ContestFixture::class, ProblemFixture::class];
    }
}
