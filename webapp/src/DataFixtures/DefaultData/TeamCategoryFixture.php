<?php declare(strict_types=1);

namespace App\DataFixtures\DefaultData;

use App\Entity\TeamCategory;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;

class TeamCategoryFixture extends AbstractDefaultDataFixture
{
    public const SYSTEM_REFERENCE = 'system';

    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function load(ObjectManager $manager): void
    {
        $data = [
            // Name,            Sort order, Color,     Visible, External ID
            ['System',          9,          '#ff2bea', false,   'system'],
            ['Self-Registered', 8,          '#33cc44', true,    'self-registered'],
        ];

        foreach ($data as $item) {
            if (!($category = $manager->getRepository(TeamCategory::class)->findOneBy(['name' => $item[0]]))) {
                $category = (new TeamCategory())
                    ->setName($item[0])
                    ->setSortorder($item[1])
                    ->setColor($item[2])
                    ->setVisible($item[3])
                    ->setExternalid($item[4]);
                $manager->persist($category);
                $manager->flush();
            } else {
                $this->logger->info('Category %s already exists, not created', [ $item[0] ]);
            }

            // Make sure we have a reference to the system category, since we need it to create the admin user.
            if ($item[0] === 'System') {
                $this->addReference(static::SYSTEM_REFERENCE, $category);
            }
        }
    }
}
