<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index on judging_run.endtime for the judgehost timeline';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX endtime ON judging_run (endtime)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX endtime ON judging_run');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
