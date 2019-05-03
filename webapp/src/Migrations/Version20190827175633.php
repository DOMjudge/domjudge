<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20190827175633 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'add flag to filter compile files to language table';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE language ADD filter_compiler_files TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Whether to filter the files passed to the compiler by the extension list\'');
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE language DROP filter_compiler_files');
    }
}
