<?php declare(strict_types=1);

namespace App\DataFixtures\DefaultData;

use App\Entity\Role;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;

class RoleFixture extends AbstractDefaultDataFixture
{
    public const ADMIN_REFERENCE = 'admin';
    public const JUDGEHOST_REFERENCE = 'judgehost';
    public const TEAM_REFERENCE = 'team';

    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function load(ObjectManager $manager): void
    {
        // Mapping from role to description
        $roles = [
            'admin'             => 'Administrative User',
            'jury'              => 'Jury User',
            'team'              => 'Team Member',
            'balloon'           => 'Balloon runner',
            'judgehost'         => '(Internal/System) Judgehost',
            'api_reader'        => 'API reader',
            'api_writer'        => 'API writer',
            'api_source_reader' => 'Source code reader',
            'clarification_rw'  => 'Clarification handler',
        ];
        foreach ($roles as $roleName => $description) {
            if (!($role = $manager->getRepository(Role::class)->findOneBy(['dj_role' => $roleName]))) {
                $role = (new Role())
                    ->setDjRole($roleName)
                    ->setDescription($description);
                $manager->persist($role);
                $manager->flush();
            } else {
                $this->logger->info('Role %s already exists, not created', [ $roleName ]);
            }

            // Make sure we have a reference to the admin and judgehost roles, since we need them to create the
            // default users.
            // Although the role name and reference have the same value, they are different things: the name is
            // from the role list while the constant is the reference name. We could rename the constants without
            // breaking anything.
            if ($roleName === 'admin') {
                $this->addReference(static::ADMIN_REFERENCE, $role);
            } elseif ($roleName === 'judgehost') {
                $this->addReference(static::JUDGEHOST_REFERENCE, $role);
            } elseif ($roleName === 'team') {
                $this->addReference(static::TEAM_REFERENCE, $role);
            }
        }
    }
}
