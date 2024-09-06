<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231120225210 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove leading dots from extensions in the `language` table';
    }

    public function up(Schema $schema): void
    {
        $languages = $this->connection->fetchAllAssociative('SELECT langid, extensions FROM language');

        foreach ($languages as $language) {
            $extensions = json_decode($language['extensions'], true);

            $updated = false;
            foreach ($extensions as &$extension) {
                if (strpos($extension, '.') === 0) {
                    $extension = ltrim($extension, '.');
                    $updated = true;
                }
            }

            if ($updated) {
                $newExtensionsJson = json_encode($extensions);
                $this->addSql('UPDATE language SET extensions = :extensions WHERE langid = :langid', [
                    'extensions' => $newExtensionsJson,
                    'langid' => $language['langid'],
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        // This migration is not reversible
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
