<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240629154640 extends AbstractMigration
{
    private const NEW_ROLES = ['api_problem_change' => 'API Problem Changer'];

    public function getDescription(): string
    {
        return 'Add new roles to the database.';
    }

    public function up(Schema $schema): void
    {
        foreach(self::NEW_ROLES as $role => $description) {
            $existingRole = $this->connection->fetchAssociative('SELECT * FROM role WHERE role = :role', ['role' => $role]);
            if (!$existingRole) {
                $this->addSql(
                    'INSERT INTO role (`role`, `description`) VALUES (:role, :desc)',
                    ['role' => $role, 'desc' => $description]
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        foreach(array_keys(self::NEW_ROLES) as $role) {
            $this->addSql('DELETE FROM role WHERE role = ' . $role );
        }
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
