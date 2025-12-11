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
            // ID           external ID        name                        extensions                 require  entry point   allow   allow   time   compile              compiler version      runner version
            //                                                                                    entry point  description   submit  judge   factor script               command               command
            ['adb',         'ada',             'Ada',                      ['adb', 'ads'],              false, null,         false,  true,   1,     'adb',               'gnatmake --version', ''],
            ['awk',         'awk',             'AWK',                      ['awk'],                     false, null,         false,  true,   1,     'awk',               'awk --version',      'awk --version'],
            ['bash',        'bash',            'Bash shell',               ['bash'],                    false, 'Main file',  false,  true,   1,     'bash',              'bash --version',     'bash --version'],
            ['c',           'c',               'C',                        ['c'],                       false, null,         true,   true,   1,     'c',                 'gcc --version',      ''],
            ['cpp',         'cpp',             'C++',                      ['cpp', 'cc', 'cxx', 'c++'], false, null,         true,   true,   1,     'cpp',               'g++ --version',      ''],
            ['csharp',      'csharp',          'C#',                       ['csharp', 'cs'],            false, null,         false,  true,   1,     'csharp',            'mcs --version',      'mono --version'],
            ['f95',         'f95',             'Fortran',                  ['f95', 'f90'],              false, null,         false,  true,   1,     'f95',               'gfortran --version', ''],
            ['hs',          'haskell',         'Haskell',                  ['hs', 'lhs'],               false, null,         false,  true,   1,     'hs',                'ghc --version',      ''],
            ['java',        'java',            'Java',                     ['java'],                    false, 'Main class', true,   true,   1,     'java_javac_detect', 'javac -version',     'java -version'],
            ['js',          'javascript',      'JavaScript',               ['js', 'mjs'],               false, 'Main file',  false,  true,   1,     'js',                'nodejs --version',   'nodejs --version'],
            ['lua',         'lua',             'Lua',                      ['lua'],                     false, null,         false,  true,   1,     'lua',               'luac -v',            'lua -v'],
            ['kt',          'kotlin',          'Kotlin',                   ['kt'],                      true,  'Main class', false,  true,   1,     'kt',                'kotlinc -version',   'kotlin -version'],
            ['pas',         'pascal',          'Pascal',                   ['pas', 'p'],                false, 'Main file',  false,  true,   1,     'pas',               'fpc -iW',            ''],
            ['pl',          'pl',              'Perl',                     ['pl'],                      false, 'Main file',  false,  true,   1,     'pl',                'perl -v',            'perl -v'],
            ['plg',         'prolog',          'Prolog',                   ['plg'],                     false, 'Main file',  false,  true,   1,     'plg',               'swipl --version',    ''],
            ['py3',         'python3',         'Python 3 (using PyPy)',    ['py'],                      false, 'Main file',  true,   true,   1,     'py3',               'pypy3 --version',    'pypy3 --version'],
            ['py3-cpython', 'python3-cpython', 'Python 3 (using CPython)', ['py'],                      false, 'Main file',  true,   true,   1,     'py3-cpython',       'python3 --version',  'python3 --version'],
            ['ocaml',       'ocaml',           'OCaml',                    ['ml'],                      false, null,         false,  true,   1,     'ocaml',             'ocamlopt --version', ''],
            ['r',           'r',               'R',                        ['R'],                       false, 'Main file',  false,  true,   1,     'r',                 'Rscript --version',  'Rscript --version'],
            ['rb',          'ruby',            'Ruby',                     ['rb'],                      false, 'Main file',  false,  true,   1,     'rb',                'ruby --version',     'ruby --version'],
            ['rs',          'rust',            'Rust',                     ['rs'],                      false, null,         false,  true,   1,     'rs',                'rustc --version',    ''],
            ['scala',       'scala',           'Scala',                    ['scala'],                   false, null,         false,  true,   1,     'scala',             'scalac -version',    'scala -version'],
            ['sh',          'sh',              'POSIX shell',              ['sh'],                      false, 'Main file',  false,  true,   1,     'sh',                'md5sum /bin/sh',                   'md5sum /bin/sh'],
            ['swift',       'swift',           'Swift',                    ['swift'],                   false, 'Main file',  false,  true,   1,     'swift',             'swiftc --version',   ''],
        ];

        foreach ($data as $item) {
            // Note: we only create the language if it doesn't exist yet.
            // If it does, we will not update the data
            if (!$manager->getRepository(Language::class)->find($item[0])) {
                $file = sprintf('%s/files/defaultdata/%s.zip',
                    $this->sqlDir, $item[9]
                );
                if (!($executable = $manager->getRepository(Executable::class)->find($item[9]))) {
                    $executable = (new Executable())
                        ->setExecid($item[9])
                        ->setDescription($item[9])
                        ->setType('compile')
                        ->setImmutableExecutable($this->createImmutableExecutable($file));
                    $manager->persist($executable);
                } else {
                    $this->logger->info('Executable %s already exists, not created', [ $item[9] ]);
                }
                $language = (new Language())
                    ->setLangid($item[0])
                    ->setExternalid($item[1])
                    ->setName($item[2])
                    ->setExtensions($item[3])
                    ->setRequireEntryPoint($item[4])
                    ->setEntryPointDescription($item[5])
                    ->setAllowSubmit($item[6])
                    ->setAllowJudge($item[7])
                    ->setTimeFactor($item[8])
                    ->setCompileExecutable($executable);
                if (!empty($item[10])) {
                    $language->setCompilerVersionCommand($item[10]);
                }
                if (!empty($item[11])) {
                    $language->setRunnerVersionCommand($item[11]);
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
