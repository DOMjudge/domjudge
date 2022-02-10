<?php declare(strict_types=1);

namespace App\DataFixtures\DefaultData;

use App\Entity\Team;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;

class TeamFixture extends AbstractDefaultDataFixture implements DependentFixtureInterface
{
    public const DOMJUDGE_REFERENCE = 'domjudge';

    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function load(ObjectManager $manager): void
    {
        if (!($team = $manager->getRepository(Team::class)->findOneBy(['name' => 'DOMjudge']))) {
            $team = (new Team())
                ->setName('DOMjudge')
                ->setExternalid('domjudge')
                ->setCategory($this->getReference(TeamCategoryFixture::SYSTEM_REFERENCE));
            $manager->persist($team);
        } else {
            $this->logger->info('Team DOMjudge already exists, not created');
        }
        $manager->flush();

        $this->addReference(self::DOMJUDGE_REFERENCE, $team);
    }

    public function getDependencies(): array
    {
        return [TeamCategoryFixture::class];
    }
}
