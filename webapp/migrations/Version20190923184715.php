<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190923184715 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Change enable_printing to print_command';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql(<<<SQL
UPDATE configuration
  SET name        = 'print_command',
      value       = IF(value = 0, '""', '"enscript -a 0-10 -f Courier9 [file] 2>&1"'),
      type        = 'string',
      description = 'If not empty, enable teams and jury to send source code to this command. See admin manual for allowed arguments.'
  WHERE name      = 'enable_printing';
SQL
);
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql(<<<SQL
UPDATE configuration
  SET name        = 'enable_printing',
      value       = IF(value = '""', '0', '1'),
      type        = 'bool',
      description = 'Enable teams and jury to send source code to a printer via the DOMjudge web interface.'
  WHERE name      = 'print_command';
SQL
);
    }
}
