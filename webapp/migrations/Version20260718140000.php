<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260718140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add interaction log to testcase content';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE testcase_content ADD interaction LONGBLOB DEFAULT NULL COMMENT \'Interaction log illustrating this sample testcase\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE testcase_content DROP interaction');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
