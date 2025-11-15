<?php declare(strict_types=1);

namespace App\DataFixtures\DefaultData;

use App\Entity\Executable;
use App\Entity\ImmutableExecutable;
use App\Service\DOMJudgeService;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use ZipArchive;

class ExecutableFixture extends AbstractDefaultDataFixture
{
    public function __construct(
        #[Autowire('%domjudge.sqldir%')]
        protected readonly string $sqlDir,
        protected readonly DOMJudgeService $dj,
        protected readonly LoggerInterface $logger
    ) {}

    public function load(ObjectManager $manager): void
    {
        $data = [
            // ID,         description,               type
            ['compare',        'default compare script',                  'compare'],
            ['full_debug',     'default full debug script',               'debug'],
            ['java_javac',     'java_javac',                              'compile'],
            ['judgehost_info', 'generic information about the judgehost', 'generic_task'],
            ['chroot_upgrade', 'upgrade chroot packages',                 'generic_task'],
            ['run',            'default run script',                      'run'],
        ];

        foreach ($data as $item) {
            // Note: we only create the executable if it doesn't exist yet.
            // If it does, we will not update the data
            if (!$manager->getRepository(Executable::class)->find($item[0])) {
                $file = sprintf('%s/files/defaultdata/%s.zip',
                    $this->sqlDir, $item[0]
                );
                $executable = (new Executable())
                    ->setExecid($item[0])
                    ->setDescription($item[1])
                    ->setType($item[2])
                    ->setImmutableExecutable($this->createImmutableExecutable($file));
                $manager->persist($executable);
            } else {
                $this->logger->info('Executable %s already exists, not created', [ $item[0] ]);
            }
        }
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['default', 'gatling'];
    }

    private function createImmutableExecutable(string $filename): ImmutableExecutable
    {
        $zip = new ZipArchive();
        $zip->open($filename, ZipArchive::CHECKCONS);
        return $this->dj->createImmutableExecutable($zip);
    }
}
