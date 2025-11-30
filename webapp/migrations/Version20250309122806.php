<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250309122806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sort keys to rankcache, allowing us to support different scoring functions efficiently and elegantly';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rankcache ADD sort_key_public TEXT DEFAULT \'\' NOT NULL COMMENT \'Opaque sort key for public audience.\', ADD sort_key_restricted TEXT DEFAULT \'\' NOT NULL COMMENT \'Opaque sort key for restricted audience.\'');
        $this->addSql('CREATE INDEX sortKeyPublic ON rankcache (sort_key_public(768))');
        $this->addSql('CREATE INDEX sortKeyRestricted ON rankcache (sort_key_restricted(768))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX sortKeyPublic ON rankcache');
        $this->addSql('DROP INDEX sortKeyRestricted ON rankcache');
        $this->addSql('ALTER TABLE rankcache DROP sort_key_public, DROP sort_key_restricted');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
