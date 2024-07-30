<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230508170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Compute hashes of executable files';
        // This migration is only required for databases that were migrated
        // from DOMjudge 7.x using certain DOMjudge versions in the range 8.0.0
        // to 8.3.0. Databases migrated from 7.x straight to 8.3.1+ will have
        // the hashes computed by Version20201219154651.php.
    }

    public function up(Schema $schema): void
    {
        $executableFiles = $this->connection->fetchAllAssociative('SELECT execfileid, file_content FROM executable_file WHERE hash IS NULL');
        foreach ($executableFiles as $file) {
            $this->addSql('UPDATE executable_file SET hash = :hash WHERE execfileid = :id', [
                'hash' => md5($file['file_content']),
                'id' => $file['execfileid']
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        // We don't handle this case
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
