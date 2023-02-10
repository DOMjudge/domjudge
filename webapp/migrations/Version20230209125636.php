<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230209125636 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prepare database for runtime tiebreaker.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contest ADD order_by_runtime TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Is runtime used as tiebreaker instead of penalty?\'');
        $this->addSql('DROP INDEX order_public ON rankcache');
        $this->addSql('DROP INDEX order_restricted ON rankcache');
        $this->addSql('ALTER TABLE rankcache ADD totalruntime_restricted INT DEFAULT 0 NOT NULL COMMENT \'Total runtime in milliseconds (restricted audience)\', ADD totalruntime_public INT DEFAULT 0 NOT NULL COMMENT \'Total runtime in milliseconds (public)\'');
        $this->addSql('CREATE INDEX order_public ON rankcache (cid, points_public, totaltime_public, totalruntime_public)');
        $this->addSql('CREATE INDEX order_restricted ON rankcache (cid, points_restricted, totaltime_restricted, totalruntime_restricted)');
        $this->addSql('ALTER TABLE scorecache ADD runtime_restricted INT DEFAULT 0 NOT NULL COMMENT \'Runtime in milliseconds (restricted audience)\', ADD runtime_public INT DEFAULT 0 NOT NULL COMMENT \'Runtime in milliseconds (public)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contest DROP order_by_runtime');
        $this->addSql('ALTER TABLE scorecache DROP runtime_restricted, DROP runtime_public');
        $this->addSql('DROP INDEX order_restricted ON rankcache');
        $this->addSql('DROP INDEX order_public ON rankcache');
        $this->addSql('ALTER TABLE rankcache DROP totalruntime_restricted, DROP totalruntime_public');
        $this->addSql('CREATE INDEX order_restricted ON rankcache (cid, points_restricted, totaltime_restricted)');
        $this->addSql('CREATE INDEX order_public ON rankcache (cid, points_public, totaltime_public)');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
