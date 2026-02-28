<?php declare(strict_types=1);

namespace App\DataFixtures\DefaultData;

use App\Entity\Executable;
use App\Entity\ImmutableExecutable;
use App\Entity\Language;
use App\Service\DOMJudgeService;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use ZipArchive;

class LanguageFixture extends AbstractDefaultDataFixture
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
            // external ID   name           extensions                 require  entry point   allow   allow   time   compile              compiler version      runner version
            //                                                     entry point  description   submit  judge   factor script               command               command
            ['ada',        'Ada',         ['adb', 'ads'],              false, null,         false,  true,   1,     'adb',               'gnatmake --version', ''],
            ['awk',        'AWK',         ['awk'],                     false, null,         false,  true,   1,     'awk',               'awk --version',      'awk --version'],
            ['bash',       'Bash shell',  ['bash'],                    false, 'Main file',  false,  true,   1,     'bash',              'bash --version',     'bash --version'],
            ['c',          'C',           ['c'],                       false, null,         true,   true,   1,     'c',                 'gcc --version',      ''],
            ['cpp',        'C++',         ['cpp', 'cc', 'cxx', 'c++'], false, null,         true,   true,   1,     'cpp',               'g++ --version',      ''],
            ['csharp',     'C#',          ['csharp', 'cs'],            false, null,         false,  true,   1,     'csharp',            'mcs --version',      'mono --version'],
            ['f95',        'Fortran',     ['f95', 'f90'],              false, null,         false,  true,   1,     'f95',               'gfortran --version', ''],
            ['haskell',    'Haskell',     ['hs', 'lhs'],               false, null,         false,  true,   1,     'hs',                'ghc --version',      ''],
            ['java',       'Java',        ['java'],                    false, 'Main class', true,   true,   1,     'java_javac_detect', 'javac -version',     'java -version'],
            ['javascript', 'JavaScript',  ['js', 'mjs'],               false, 'Main file',  false,  true,   1,     'js',                'nodejs --version',   'nodejs --version'],
            ['lua',        'Lua',         ['lua'],                     false, null,         false,  true,   1,     'lua',               'luac -v',            'lua -v'],
            ['kotlin',     'Kotlin',      ['kt'],                      true,  'Main class', false,  true,   1,     'kt',                'kotlinc -version',   'kotlin -version'],
            ['pascal',     'Pascal',      ['pas', 'p'],                false, 'Main file',  false,  true,   1,     'pas',               'fpc -iW',            ''],
            ['pl',         'Perl',        ['pl'],                      false, 'Main file',  false,  true,   1,     'pl',                'perl -v',            'perl -v'],
            ['prolog',     'Prolog',      ['plg'],                     false, 'Main file',  false,  true,   1,     'plg',               'swipl --version',    ''],
            ['python3',    'Python 3',    ['py'],                      false, 'Main file',  true,   true,   1,     'py3',               'pypy3 --version',    'pypy3 --version'],
            ['ocaml',      'OCaml',       ['ml'],                      false, null,         false,  true,   1,     'ocaml',             'ocamlopt --version', ''],
            ['r',          'R',           ['R'],                       false, 'Main file',  false,  true,   1,     'r',                 'Rscript --version',  'Rscript --version'],
            ['ruby',       'Ruby',        ['rb'],                      false, 'Main file',  false,  true,   1,     'rb',                'ruby --version',     'ruby --version'],
            ['rust',       'Rust',        ['rs'],                      false, null,         false,  true,   1,     'rs',                'rustc --version',    ''],
            ['scala',      'Scala',       ['scala'],                   false, null,         false,  true,   1,     'scala',             'scalac -version',    'scala -version'],
            ['sh',         'POSIX shell', ['sh'],                      false, 'Main file',  false,  true,   1,     'sh',                'md5sum /bin/sh',                   'md5sum /bin/sh'],
            ['swift',      'Swift',       ['swift'],                   false, 'Main file',  false,  true,   1,     'swift',             'swiftc --version',   ''],
        ];

        foreach ($data as $item) {
            // Note: we only create the language if it doesn't exist yet.
            // If it does, we will not update the data
            if (!$manager->getRepository(Language::class)->findOneBy(['externalid' => $item[0]])) {
                $file = sprintf('%s/files/defaultdata/%s.zip',
                    $this->sqlDir, $item[8]
                );
                if (!($executable = $manager->getRepository(Executable::class)->find($item[8]))) {
                    $executable = (new Executable())
                        ->setExecid($item[8])
                        ->setDescription($item[8])
                        ->setType('compile')
                        ->setImmutableExecutable($this->createImmutableExecutable($file));
                    $manager->persist($executable);
                } else {
                    $this->logger->info('Executable %s already exists, not created', [ $item[8] ]);
                }
                $language = (new Language())
                    ->setExternalid($item[0])
                    ->setName($item[1])
                    ->setExtensions($item[2])
                    ->setRequireEntryPoint($item[3])
                    ->setEntryPointDescription($item[4])
                    ->setAllowSubmit($item[5])
                    ->setAllowJudge($item[6])
                    ->setTimeFactor($item[7])
                    ->setCompileExecutable($executable);
                if (!empty($item[9])) {
                    $language->setCompilerVersionCommand($item[9]);
                }
                if (!empty($item[10])) {
                    $language->setRunnerVersionCommand($item[10]);
                }
                $manager->persist($language);
            } else {
                $this->logger->info('Language %s already exists, not created', [ $item[0] ]);
            }
        }
        $manager->flush();
    }

    private function createImmutableExecutable(string $filename): ImmutableExecutable
    {
        $zip = new ZipArchive();
        $zip->open($filename, ZipArchive::CHECKCONS);
        return $this->dj->createImmutableExecutable($zip);
    }
}
