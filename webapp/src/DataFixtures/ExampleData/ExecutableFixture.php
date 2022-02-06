<?php declare(strict_types=1);

namespace App\DataFixtures\ExampleData;

use App\Entity\Executable;
use App\Entity\ImmutableExecutable;
use App\Service\DOMJudgeService;
use Doctrine\Persistence\ObjectManager;
use ZipArchive;

class ExecutableFixture extends AbstractExampleDataFixture
{
    const BOOLFIND_CMP_REFERENCE = 'boolfind-cmp';
    const BOOLFIND_RUN_REFERENCE = 'boolfind-run';

    protected string $sqlDir;
    protected DOMJudgeService $dj;

    public function __construct(string $sqlDir, DOMJudgeService $dj)
    {
        $this->sqlDir = $sqlDir;
        $this->dj = $dj;
    }

    public function load(ObjectManager $manager): void
    {
        $boolfindRunFile = sprintf(
            '%s/files/examples/boolfind_run.zip',
            $this->sqlDir
        );
        $boolfindRun = new Executable();
        $boolfindRun
            ->setExecid('boolfind_run')
            ->setDescription('boolfind run and compare')
            ->setType('run')
            ->setImmutableExecutable($this->createImmutableExecutable($boolfindRunFile));

        $manager->persist($boolfindRun);
        $manager->flush();

        $this->addReference(self::BOOLFIND_RUN_REFERENCE, $boolfindRun);
    }

    private function createImmutableExecutable(string $filename): ImmutableExecutable
    {
        $zip = new ZipArchive();
        $zip->open($filename, ZipArchive::CHECKCONS);
        return $this->dj->createImmutableExecutable($zip);
    }

    public static function getGroups(): array
    {
        return ['example', 'gatling'];
    }
}
