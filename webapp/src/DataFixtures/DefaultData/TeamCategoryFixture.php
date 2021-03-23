<?php declare(strict_types=1);

namespace App\DataFixtures\DefaultData;

use App\Entity\TeamCategory;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;

class TeamCategoryFixture extends AbstractDefaultDataFixture
{
    public const SYSTEM_REFERENCE = 'system';

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager)
    {
        $data = [
            // Name,            Sort order, Color,     Visible
            ['System',          9,          '#ff2bea', false],
            ['Self-Registered', 8,          '#33cc44', true],
        ];

        foreach ($data as $item) {
            if (!($category = $manager->getRepository(TeamCategory::class)->findOneBy(['name' => $item[0]]))) {
                $category = (new TeamCategory())
                    ->setName($item[0])
                    ->setSortorder($item[1])
                    ->setColor($item[2])
                    ->setVisible($item[3]);
                $manager->persist($category);
                $manager->flush();
            } else {
                $this->logger->info('Category %s already exists, not created', [ $item[0] ]);
            }

            // Make sure we have a reference to the system category, since we need it to create the admin user
            if ($item[0] === 'System') {
                $this->addReference(static::SYSTEM_REFERENCE, $category);
            }
        }
    }
}
