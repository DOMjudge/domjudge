<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Executable;
use App\Entity\ExecutableFile;
use App\Entity\ImmutableExecutable;
use Doctrine\Persistence\ObjectManager;
use ZipArchive;

/**
 * Class ExecutableFixture
 * @package App\DataFixtures
 */
class ExecutableFixture extends AbstractExampleDataFixture
{
    const BOOLFIND_CMP_REFERENCE = 'boolfind-cmp';
    const BOOLFIND_RUN_REFERENCE = 'boolfind-run';

    /**
     * @var string
     */
    protected $sqlDir;

    /**
     * ExecutableFixture constructor.
     * @param string $sqlDir
     */
    public function __construct(string $sqlDir)
    {
        $this->sqlDir = $sqlDir;
    }

    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager)
    {
        $boolfindCompareFile = sprintf(
            '%s/files/examples/boolfind_cmp.zip',
            $this->sqlDir
        );
        $boolfindCompare     = new Executable();
        $boolfindCompare
            ->setExecid('boolfind_cmp')
            ->setDescription('boolfind comparator')
            ->setType('compare')
            ->setImmutableExecutable($this->createImmutableExecutable($boolfindCompareFile, $manager));

        $boolfindRunFile = sprintf(
            '%s/files/examples/boolfind_run.zip',
            $this->sqlDir
        );
        $boolfindRun     = new Executable();
        $boolfindRun
            ->setExecid('boolfind_run')
            ->setDescription('boolfind run script')
            ->setType('run')
            ->setImmutableExecutable($this->createImmutableExecutable($boolfindRunFile, $manager));

        $manager->persist($boolfindCompare);
        $manager->persist($boolfindRun);
        $manager->flush();

        $this->addReference(self::BOOLFIND_CMP_REFERENCE, $boolfindCompare);
        $this->addReference(self::BOOLFIND_RUN_REFERENCE, $boolfindRun);
    }

    // TODO: Check whether it's possible to use services in fixtures and reduce code duplication.
    private function createImmutableExecutable(string $filename, ObjectManager $manager): ImmutableExecutable
    {
        $zip = new ZipArchive();
        $zip->open($filename, ZIPARCHIVE::CHECKCONS);

        $propertyFile = 'domjudge-executable.ini';
        $immutableExecutable = new ImmutableExecutable();
        $manager->persist($immutableExecutable);
        $rank = 0;
        for ($idx = 0; $idx < $zip->numFiles; $idx++) {
            $filename = $zip->getNameIndex($idx);
            if ($filename === $propertyFile) {
                continue;
            }
            $executableFile = new ExecutableFile();
            $executableFile
                ->setRank($rank)
                ->setFilename($filename)
                ->setFileContent($zip->getFromIndex($idx))
                ->setIsExecutable(in_array($filename, ['build', 'run']))
                ->setImmutableExecutable($immutableExecutable);
            $manager->persist($executableFile);
            $rank++;
        }
        return $immutableExecutable;
    }
}
