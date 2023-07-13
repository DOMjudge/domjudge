<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230707160739 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
CREATE TABLE `blog_post` (
    `blogpostid` int(4) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
    `slug` varchar(255) NOT NULL COMMENT 'Unique slug',
    `publishtime` datetime NOT NULL COMMENT 'Time sent',
    `author` varchar(255) DEFAULT NULL COMMENT 'Name of the post author',
    `title` varchar(511) NOT NULL COMMENT 'Blog post title',
    `subtitle` longtext NOT NULL COMMENT 'Blog post subtitle',
    `thumbnail_file_name` varchar(255) NOT NULL COMMENT 'Thumbnail file name',
    `body` longtext NOT NULL COMMENT 'Blog post text',
    PRIMARY KEY  (`blogpostid`),
    UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Public blog posts sent by the jury'
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE blog_post');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
