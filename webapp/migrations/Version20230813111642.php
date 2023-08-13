<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230813111642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the {compiler,runner} run/build command (+ arguments) to the database.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE language ADD compiler_command VARCHAR(255) DEFAULT NULL COMMENT \'Compiler command\', ADD runner_command VARCHAR(255) DEFAULT NULL COMMENT \'Runner command\', ADD compiler_command_args VARCHAR(255) DEFAULT NULL COMMENT \'Compiler command arguments\', ADD runner_command_args VARCHAR(255) DEFAULT NULL COMMENT \'Runner command arguments\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE language DROP compiler_command, DROP runner_command, DROP compiler_command_args, DROP runner_command_args');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
