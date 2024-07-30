<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240511091916 extends AbstractMigration
{
    private const COMPILER_VERSION_COMMAND =   ['adb'=> 'gnatmake --version',
                                                'awk'=> 'awk --version',
                                                'bash'=> 'bash --version',
                                                'c' => 'gcc --version',
                                                'cpp' => 'g++ --version',
                                                'csharp' => 'mcs --version',
                                                'f95' =>  'gfortran --version',
                                                'hs' =>  'ghc --version',
                                                'java' => 'javac --version',
                                                'js' =>  'nodejs --version',
                                                'kt' =>  'kotlinc --version',
                                                'lua' => 'luac -v',
                                                'pas' => 'fpc -iW',
                                                'pl' => 'perl -v',
                                                'plg' =>  'swipl --version',
                                                'py3' => 'pypy3 --version',
                                                'ocaml' => 'ocamlopt --version',
                                                'r' => 'Rscript --version',
                                                'rb' => 'ruby --version',
                                                'rs' => 'rustc --version',
                                                'scala' => 'scalac --version',
                                                'sh' => 'md5sum /bin/sh',
                                                'swift' => 'swiftc --version'];

    private const RUNNER_VERSION_COMMAND = ['awk'=> 'awk --version',
                                            'bash'=> 'bash --version',
                                            'csharp' => 'mono --version',
                                            'java' => 'java --version',
                                            'js' =>  'nodejs --version',
                                            'kt' =>  'kotlin --version',
                                            'lua' => 'lua -v',
                                            'pl' => 'perl -v',
                                            'py3' => 'pypy3 --version',
                                            'r' => 'Rscript --version',
                                            'rb' => 'ruby --version',
                                            'scala' => 'scala --version',
                                            'sh' => 'md5sum /bin/sh'];

    public function getDescription(): string
    {
        return 'Fill default version command for compiler/runner.';
    }

    public function up(Schema $schema): void
    {
        foreach (self::COMPILER_VERSION_COMMAND as $langid => $versionCommand) {
            $this->addSql(
                "UPDATE language SET compiler_version_command = :compiler_version_command WHERE langid = :langid AND compiler_version_command IS NULL",
                ['compiler_version_command' => $versionCommand, 'langid' => $langid]
            );
        }
        foreach (self::RUNNER_VERSION_COMMAND as $langid => $versionCommand) {
            $this->addSql(
                "UPDATE language SET runner_version_command = :compiler_version_command WHERE langid = :langid AND runner_version_command IS NULL",
                ['compiler_version_command' => $versionCommand, 'langid' => $langid]
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::COMPILER_VERSION_COMMAND as $langid => $versionCommand) {
            $this->addSql(
                "UPDATE language SET compiler_version_command = NULL WHERE langid = :langid AND compiler_version_command = :compiler_version_command",
                ['compiler_version_command' => $versionCommand, 'langid' => $langid]
            );
        }
        foreach (self::RUNNER_VERSION_COMMAND as $langid => $versionCommand) {
            $this->addSql(
                "UPDATE language SET runner_version_command = NULL WHERE langid = :langid AND runner_version_command = :compiler_version_command",
                ['compiler_version_command' => $versionCommand, 'langid' => $langid]
            );
        }
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
