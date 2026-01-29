<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add composite index on judgetask for the "continue previous work" polling query.
 */
final class Version20260129120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite index (judgehostid, type, starttime, jobid) on judgetask to optimize judgehost polling.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX judgehostid_type_starttime ON judgetask (judgehostid, type, starttime, jobid)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX judgehostid_type_starttime ON judgetask');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
