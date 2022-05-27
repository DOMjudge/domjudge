<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220528195758 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change presentation-error verdicts to wrong-answer. This verdict has not been used since DOMjudge 5.0';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE judging_run SET runresult = \'wrong-answer\' WHERE runresult = \'presentation-error\'');
        $this->addSql('UPDATE judging SET result = \'wrong-answer\' WHERE result = \'presentation-error\'');
    }

    public function down(Schema $schema): void
    {
        // We lost the information which runs might have had presentation-error
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
