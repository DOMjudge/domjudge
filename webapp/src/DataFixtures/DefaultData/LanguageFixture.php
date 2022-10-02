<?php declare(strict_types=1);

namespace App\DataFixtures\DefaultData;

use App\Entity\Executable;
use App\Entity\ImmutableExecutable;
use App\Entity\Language;
use App\Service\DOMJudgeService;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;
use ZipArchive;

class LanguageFixture extends AbstractDefaultDataFixture
{
    protected string $sqlDir;
    protected DOMJudgeService $dj;
    protected LoggerInterface $logger;

    public function __construct(string $sqlDir, DOMJudgeService $dj, LoggerInterface $logger)
    {
        $this->sqlDir = $sqlDir;
        $this->dj     = $dj;
        $this->logger = $logger;
    }

    public function load(ObjectManager $manager): void
    {
        $data = [
            // ID      external ID   name           extensions                 require  entry point   allow   allow   time   compile
            //                                                             entry point  description   submit  judge   factor script
            ['adb',    'ada',        'Ada',         ['adb', 'ads'],              false, null,         false,  true,   1,     'adb'],
            ['awk',    'awk',        'AWK',         ['awk'],                     false, null,         false,  true,   1,     'awk'],
            ['bash',   'bash',       'Bash shell',  ['bash'],                    false, 'Main file',  false,  true,   1,     'bash'],
            ['c',      'c',          'C',           ['c'],                       false, null,         true,   true,   1,     'c'],
            ['cpp',    'cpp',        'C++',         ['cpp', 'cc', 'cxx', 'c++'], false, null,         true,   true,   1,     'cpp'],
            ['csharp', 'csharp',     'C#',          ['csharp', 'cs'],            false, null,         false,  true,   1,     'csharp'],
            ['f95',    'f95',        'Fortran',     ['f95', 'f90'],              false, null,         false,  true,   1,     'f95'],
            ['hs',     'haskell',    'Haskell',     ['hs', 'lhs'],               false, null,         false,  true,   1,     'hs'],
            ['java',   'java',       'Java',        ['java'],                    false, 'Main class', true,   true,   1,     'java_javac_detect'],
            ['js',     'javascript', 'JavaScript',  ['js'],                      false, 'Main file',  false,  true,   1,     'js'],
            ['lua',    'lua',        'Lua',         ['lua'],                     false, null,         false,  true,   1,     'lua'],
            ['kt',     'kotlin',     'Kotlin',      ['kt'],                      true,  'Main class', false,  true,   1,     'kt'],
            ['pas',    'pascal',     'Pascal',      ['pas', 'p'],                false, 'Main file',  false,  true,   1,     'pas'],
            ['pl',     'pl',         'Perl',        ['pl'],                      false, 'Main file',  false,  true,   1,     'pl'],
            ['plg',    'prolog',     'Prolog',      ['plg'],                     false, 'Main file',  false,  true,   1,     'plg'],
            ['py3',    'python3',    'Python 3',    ['py'],                      false, 'Main file',  true,   true,   1,     'py3'],
            ['r',      'r',          'R',           ['R'],                       false, 'Main file',  false,  true,   1,     'r'],
            ['rb',     'ruby',       'Ruby',        ['rb'],                      false, 'Main file',  false,  true,   1,     'rb'],
            ['rs',     'rust',       'Rust',        ['rs'],                      false, null,         false,  true,   1,     'rs'],
            ['scala',  'scala',      'Scala',       ['scala'],                   false, null,         false,  true,   1,     'scala'],
            ['sh',     'sh',         'POSIX shell', ['sh'],                      false, 'Main file',  false,  true,   1,     'sh'],
            ['swift',  'swift',      'Swift',       ['swift'],                   false, 'Main file',  false,  true,   1,     'swift'],
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
