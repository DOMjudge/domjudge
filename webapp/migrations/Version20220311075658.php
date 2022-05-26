<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220311075658 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Clarify difference between internal and external freeform fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team CHANGE members publicdescription longtext');
        $this->addSql('ALTER TABLE team CHANGE comments internalcomments longtext');
        $this->addSql('ALTER TABLE team_affiliation CHANGE comments internalcomments longtext');
        $this->addSql('ALTER TABLE team MODIFY publicdescription longtext COMMENT \'Public team definition; for example: Team member names (freeform)\'');
        $this->addSql('ALTER TABLE team MODIFY internalcomments longtext COMMENT \'Internal comments about this team (jury only)\'');
        $this->addSql('ALTER TABLE team_affiliation MODIFY internalcomments longtext COMMENT \'Internal comments (jury only)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_affiliation CHANGE internalcomments comments longtext');
        $this->addSql('ALTER TABLE team CHANGE internalcomments comments longtext');
        $this->addSql('ALTER TABLE team CHANGE publicdescription members longtext');
        $this->addSql('ALTER TABLE team MODIFY members longtext COMMENT \'Team member names (freeform)\'');
        $this->addSql('ALTER TABLE team MODIFY comments longtext COMMENT \'Comments about this team\'');
        $this->addSql('ALTER TABLE team_affiliation MODIFY comments longtextCOMMENT \'Comments\'');
    }
}
