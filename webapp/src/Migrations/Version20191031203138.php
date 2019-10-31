<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191031203138 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'add .py to Python 3 extension list and remove it for Python 2';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('UPDATE language SET extensions = \'["py3","py"]\' WHERE langid = \'py3\'');
        $this->addSql('UPDATE language SET extensions = \'["py2"]\' WHERE langid = \'py2\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('UPDATE language SET extensions = \'["py2","py"]\' WHERE langid = \'py2\'');
        $this->addSql('UPDATE language SET extensions = \'["py"]\' WHERE langid = \'py3\'');
    }
}
