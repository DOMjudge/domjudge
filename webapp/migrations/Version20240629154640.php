<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240629154640 extends AbstractMigration
{
    private const NEW_ROLES = ['api_problem_editor' => 'API Problem Editor',
                               'api_contest_editor' => 'API Contest Editor'];

    public function getDescription(): string
    {
        return "Add new roles to the database.
                Problem editor can add/delete/edit anything related to problems; files, testcases.
                Contest editor can add/delete/edit the time & connected problems, but not the files
                or testcases of those problems.
                They are a subset of the ADMIN role in the API but not a proper superset of the API_WRITER
                as that also has access to push teams etc.";
    }

    public function up(Schema $schema): void
    {
        foreach (self::NEW_ROLES as $role => $description) {
            $this->addSql(
                'INSERT INTO role (`role`, `description`) VALUES (:role, :desc)',
                ['role' => $role, 'desc' => $description]
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (array_keys(self::NEW_ROLES) as $role) {
            $this->addSql('DELETE FROM role WHERE role = ' . $role );
        }
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
