<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\ExecutableFile;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230508163415 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update hashes of immutable executables';
    }

    public function up(Schema $schema): void
    {
        $immutableExecutables = $this->connection->fetchAllAssociative('SELECT immutable_execid FROM immutable_executable');
        foreach ($immutableExecutables as $immutableExecutable) {
            $files = $this->connection->fetchAllAssociative('SELECT hash, filename, is_executable FROM executable_file WHERE immutable_execid = :id', ['id' => $immutableExecutable['immutable_execid']]);
            uasort($files, fn(array $a, array $b) => strcmp($a['filename'], $b['filename']));
            $newHash = md5(
                join(
                    array_map(
                        fn(array $file) => $file['hash'] . $file['filename'] . (bool)$file['is_executable'],
                        $files
                    )
                )
            );
            $this->connection->executeQuery('UPDATE immutable_executable SET hash = :hash WHERE immutable_execid = :id', [
                'hash' => $newHash,
                'id' => $immutableExecutable['immutable_execid'],
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
