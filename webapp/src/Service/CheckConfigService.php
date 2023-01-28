<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\ContestProblem;
use App\Entity\Executable;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\Testcase;
use App\Entity\User;
use App\Utils\Utils;
use BadMethodCallException;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class CheckConfigService
 * @package App\Service
 */
class CheckConfigService
{
    protected EntityManagerInterface $em;
    protected ConfigurationService $config;
    protected DOMJudgeService $dj;
    protected EventLogService $eventLogService;
    protected ValidatorInterface $validator;
    protected RouterInterface $router;
    protected bool $debug;
    protected UserPasswordHasherInterface $passwordHasher;
    protected Stopwatch $stopwatch;

    public function __construct(
        bool $debug,
        EntityManagerInterface $em,
        ConfigurationService $config,
        DOMJudgeService $dj,
        EventLogService $eventLogService,
        RouterInterface $router,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->debug           = $debug;
        $this->em              = $em;
        $this->config          = $config;
        $this->dj              = $dj;
        $this->eventLogService = $eventLogService;
        $this->router          = $router;
        $this->validator       = $validator;
        $this->passwordHasher  = $passwordHasher;
        $this->stopwatch       = new Stopwatch();
    }

    public function runAll(): array
    {
        $results = [];

        $this->stopwatch->openSection();
        $system = [
            'php_version' => $this->checkPhpVersion(),
            'php_extensions' => $this->checkPhpExtensions(),
            'php_settings' => $this->checkPhpSettings(),
            'mysql_settings' => $this->checkMysqlSettings(),
        ];

        $results['System'] = $system;
        $this->stopwatch->stopSection('System');

        $this->stopwatch->openSection();
        $config = [
            'adminpass' => $this->checkAdminPass(),
            'comparerun' => $this->checkDefaultCompareRunExist(),
            'filesizememlimit' => $this->checkScriptFilesizevsMemoryLimit(),
            'debugdisabled' => $this->checkDebugDisabled(),
            'tmpdirwritable' => $this->checkTmpdirWritable(),
            'hashtime' => $this->checkHashTime(),
        ];

        $results['Configuration'] = $config;
        $this->stopwatch->stopSection('Configuration');

        $this->stopwatch->openSection();
        $contests = [
            'activecontests' => $this->checkContestActive(),
            'validcontests' => $this->checkContestsValidate(),
            'banners' => $this->checkContestBanners(),
        ];

        $results['Contests'] = $contests;
        $this->stopwatch->stopSection('Contests');

        $this->stopwatch->openSection();
        $pl = [
            'problems' => $this->checkProblemsValidate(),
            'languages' => $this->checkLanguagesValidate(),
        ];

        $results['Problems and languages'] = $pl;
        $this->stopwatch->stopSection('Problems and languages');

        $this->stopwatch->openSection();
        $teams = [
            'photos' => $this->checkTeamPhotos(),
            'affiliations' => $this->checkAffiliations(),
            'teamdupenames' => $this->checkTeamDuplicateNames(),
            'selfregistration' => $this->checkSelfRegistration(),
        ];

        $results['Teams'] = $teams;
        $this->stopwatch->stopSection('Teams');

        $this->stopwatch->openSection();
        $results['External identifiers'] = $this->checkAllExternalIdentifiers();
        $this->stopwatch->stopSection('External identifiers');

        return $results;
    }

    public function checkPhpVersion(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        $my = PHP_VERSION;
        $req = '7.4.0';
        $result = version_compare($my, $req, '>=');
        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'PHP version',
                'result' => ($result ? 'O' : 'E'),
                'desc' => sprintf('You have PHP version %s. The minimum required is %s', $my, $req)];
    }

    public function checkPhpExtensions(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        $required = ['json', 'mbstring', 'mysqli', 'zip', 'gd', 'intl'];
        $state = 'O';
        $remark = '';
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $state = 'E';
                $remark .= sprintf("Required PHP extension '%s' not loaded.\n", $ext);
            }
        }
        $remark = ($remark ?: 'All required and recommended extensions present.');

        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'PHP extensions',
                'result' => $state,
                'desc' => $remark];
    }

    public function checkPhpSettings(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        $sourcefiles_limit = $this->config->get('sourcefiles_limit');
        $max_files = ini_get('max_file_uploads');

        /* PHP will silently discard any files above the max_file_uploads limit,
         * so it must be at least one larger than our sourcefiles_limit so we
         * can detect this and present a proper error to the team instead of
         * just accepting it with files missing.
         */
        $result = 'O';
        if ($max_files <= $sourcefiles_limit) {
            $result = 'E';
        } elseif ($max_files < 100) {
            $result = 'W';
        }
        $desc = sprintf("PHP 'max_file_uploads' set to %s. This must be set strictly higher than the maximum number of test cases per problem and the DOMjudge configuration setting 'sourcefiles_limit' (now set to %s)", $max_files, $sourcefiles_limit);

        $sizes = [];
        $postmaxvars = ['post_max_size', 'memory_limit', 'upload_max_filesize'];
        foreach ($postmaxvars as $var) {
            // Skip 0 or empty values, and -1 which means 'unlimited'.
            if ($size = Utils::phpiniToBytes(ini_get($var))) {
                if ($size != '-1') {
                    $sizes[$var] = $size;
                }
            }
        }
        if ($result !== 'E' && min($sizes) < 52428800) {
            $result = 'W';
        }

        $desc .= "\n\n" . sprintf('PHP POST/upload filesize is limited to %s.', Utils::printsize(min($sizes)));
        $desc .= "\n\nThis limit needs to be larger than the testcases you want to upload and than the amount of program output you expect the judgedaemons to post back to DOMjudge. We recommend at least 50 MB.\n\nNote that you need to ensure that all of the following php.ini parameters are at minimum the desired size:\n";
        foreach ($postmaxvars as $var) {
            $desc .= sprintf("%s (now set to %s)\n", $var,
                    (isset($sizes[$var]) ? Utils::printsize($sizes[$var]) : "unlimited"));
        }

        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'PHP settings',
                'result' => $result,
                'desc' => $desc];
    }

    public function checkMysqlSettings(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        $r = $this->em->getConnection()->fetchAllAssociative(
            'SHOW variables WHERE Variable_name IN
                 ("innodb_log_file_size", "max_connections", "max_allowed_packet",
                  "tx_isolation", "transaction_isolation")'
        );
        $vars = [];
        foreach ($r as $row) {
            $vars[$row['Variable_name']] = $row['Value'];
        }
        # MySQL 8 has "transaction_isolation" instead of "tx_isolation".
        if (isset($vars['transaction_isolation'])) {
            $vars['tx_isolation'] = $vars['transaction_isolation'];
        }
        $max_inout_r = $this->em->getConnection()->fetchAllAssociative(
            'SELECT GREATEST(MAX(LENGTH(input)),MAX(LENGTH(output))) as max FROM testcase_content'
        );
        $max_inout = (int)reset($max_inout_r)['max'];
        $output_limit = 1024*$this->config->get('output_limit');
        if ($this->config->get('output_storage_limit') >= 0) {
            $output_limit = 1024*$this->config->get('output_storage_limit');
        }
        $max_inout = max($max_inout, $output_limit);

        $result = 'O';
        $desc = '';
        if ($vars['max_connections'] < 300) {
            $result = 'W';
            $desc .= sprintf("MySQL's max_connections is set to %s. In our experience you need at least 300, but better 1000 connections to prevent connection refusal during the contest.\n", $vars['max_connections']);
        }

        if ($vars['innodb_log_file_size'] < 10 * $max_inout) {
            $result = 'W';
            $desc .= sprintf("MySQL's innodb_log_file_size is set to %s. You may want to raise this to 10x the maximum of the test case size and output (storage) limit (now %s).\n", Utils::printsize((int)$vars['innodb_log_file_size']), Utils::printsize($max_inout));
        }

        $tx = ['REPEATABLE-READ', 'SERIALIZABLE'];
        if (!in_array($vars['tx_isolation'], $tx)) {
            $result = 'W';
            $desc .= sprintf("MySQL's transaction isolation level is set to %s. You should set this to %s to prevent data inconsistencies.\n", $vars['tx_isolation'], implode(' or ', $tx));
        }

        $recommended_max_allowed_packet = 16*1024*1024;
        if ($vars['max_allowed_packet'] < 2*$max_inout) {
            $result = 'E';
            $desc .= sprintf("MySQL's max_allowed_packet is set to %s. You may want to raise this to about twice the maximum of the test case size and output (storage) limit (currently %s).\n", Utils::printsize((int)$vars['max_allowed_packet']), Utils::printsize($max_inout));
        } elseif ($vars['max_allowed_packet'] < $recommended_max_allowed_packet) {
            $result = 'W';
            $desc .= sprintf("MySQL's max_allowed_packet is set to %s. We recommend at least 16MB.\n", Utils::printsize((int)$vars['max_allowed_packet']));
        }

        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'MySQL settings',
                'result' => $result,
                'desc' => $desc ?: 'MySQL settings are all ok'];
    }

    public function checkAdminPass(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        $res = 'O';
        $desc = 'Password for "admin" has been changed from the default.';

        /** @var User $user */
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => 'admin']);
        if ($user && password_verify('admin', $user->getPassword())) {
            $res = 'E';
            $desc = 'The "admin" user still has the default password. You should change it immediately.';
        }

        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'Non-default admin password',
                'result' => $res,
                'desc' => $desc];
    }

    public function checkDefaultCompareRunExist(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        $res = 'O';
        $desc = '';

        $scripts = ['compare', 'run'];
        foreach ($scripts as $type) {
            $scriptid = $this->config->get('default_' . $type);
            if (!$this->em->getRepository(Executable::class)->find($scriptid)) {
                $res = 'E';
                $desc .= sprintf("The default %s script '%s' does not exist.\n", $type, $scriptid);
            } else {
                $desc .= sprintf("The default %s script '%s' exists.\n", $type, $scriptid);
            }
        }

        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'Default compare and run scripts exist',
                'result' => $res,
                'desc' => $desc];
    }

    public function checkScriptFilesizevsMemoryLimit(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        if ($this->config->get('script_filesize_limit') <=
            $this->config->get('memory_limit')) {
             $result = 'W';
        } else {
             $result = 'O';
        }
        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'Compile file size vs. memory limit',
                'result' => $result,
                'desc' => 'If the script filesize limit is lower than the memory limit, then ' .
                'compilation of sources that statically allocate memory may fail. We ' .
                'recommend to include a margin to be on the safe side. The current ' .
                '"script_filesize_limit" = ' . $this->config->get('script_filesize_limit') . ' ' .
                'while "memory_limit" = ' . $this->config->get('memory_limit') . '.'
            ];
    }

    public function checkDebugDisabled(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        if ($this->debug) {
            $this->stopwatch->stop(__FUNCTION__);
            return ['caption' => 'Debugging',
                'result' => 'W',
                'desc' => "Debugging enabled.\nShould not be enabled on live systems."];
        }
        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'Debugging',
                'result' => 'O',
                'desc' => 'Debugging disabled.'];
    }

    public function checkTmpdirWritable(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        $tmpdir = $this->dj->getDomjudgeTmpDir();
        if (is_writable($tmpdir)) {
            $this->stopwatch->stop(__FUNCTION__);
            return ['caption' => 'TMPDIR writable',
                    'result' => 'O',
                    'desc' => sprintf('TMPDIR (%s) can be used to store temporary ' .
                         'files for submission diffs and edits.',
                         $tmpdir)];
        }
        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'TMPDIR writable',
                'result' => 'W',
                'desc' => sprintf('TMPDIR (%s) is not writable by the webserver; ' .
                 'Showing diffs and editing of submissions may not work.',
                 $tmpdir)];
    }

    private function randomString(int $length): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function checkHashTime(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        $tmp_user = new User();
        $counter = 0;
        $time_duration_sample = 2;
        $time_start = microtime(true);
        do {
            $plainPassword = $this->randomString(12);
            $this->passwordHasher->hashPassword($tmp_user, $plainPassword);
            $time_end = microtime(true);
            $counter++;
        } while (($time_end - $time_start) < $time_duration_sample);

        if ($counter>300) {
            $this->stopwatch->stop(__FUNCTION__);
            return ['caption' => 'User password hashing',
                'result' => 'W',
                'desc' => sprintf('Hashing is too simple for small sized contests (Did %d hashes).', $counter)];
        }
        if ($counter<100) {
            $this->stopwatch->stop(__FUNCTION__);
            return ['caption' => 'User password hashing',
                'result' => 'W',
                'desc' => sprintf('Hashing is too expensive for medium sized contests (%d done).', $counter)];
        }
        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'User password hashing',
            'result' => 'O',
            'desc' => sprintf('Hashing cost is reasonable (Did %d hashes).', $counter)];
    }

    public function checkContestActive(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        $contests = $this->dj->getCurrentContests();
        if (empty($contests)) {
            $this->stopwatch->stop(__FUNCTION__);
            return ['caption' => 'Active contests',
                    'result' => 'E',
                    'desc' => 'No currently active contests found. System will not function.'];
        }
        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'Active contests',
                'result' => 'O',
                'desc' => 'Currently active contests: ' .
                    implode(', ', array_map(
                        fn($contest) => 'c' . $contest->getCid() . ' (' . $contest->getShortname() . ')',
                        $contests
                    ))];
    }

    public function checkContestsValidate(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        // Fetch all active and future contests.
        $contests = $this->dj->getCurrentContests(null, true);

        $contesterrors = $cperrors = [];
        $result = 'O';
        foreach ($contests as $contest) {
            $cid = $contest->getCid();
            $errors = $this->validator->validate($contest);
            if (count($errors)) {
                $result = 'E';
            }
            $contesterrors[$cid] = $errors;

            $cperrors[$cid] = '';
            foreach ($contest->getProblems() as $cp) {
                if (empty($cp->getColor())) {
                    $result = ($result === 'E' ? 'E' : 'W');
                    $cperrors[$cid] .= "No color for problem " . $cp->getShortname() . " in contest c" . $cid . "\n";
                }
            }
        }

        $desc = '';
        foreach ($contesterrors as $cid => $errors) {
            $desc .= "Contest: c$cid: " .
                    (count($errors) == 0 ? 'no errors' : (string)$errors) ."\n" .$cperrors[$cid];
        }

        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'Contests validation',
            'result' => $result,
            'desc' => "Validated all active and future contests:\n\n" .
                    ($desc ?: 'No problems found.')];
    }

    public function checkContestBanners(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        // Fetch all active and future contests.
        $contests = $this->dj->getCurrentContests(null, true);

        $desc = '';
        $result = 'O';
        foreach ($contests as $contest) {
            if ($cid = $contest->getApiId($this->eventLogService)) {
                $bannerpath = $this->dj->assetPath($cid, 'contest', true);
                $contestName = 'c' . $contest->getCid() . ' (' . $contest->getShortname() . ')';
                if ($bannerpath) {
                    if (($filesize = filesize($bannerpath)) > 2 * 1024 * 1024) {
                        $result = 'W';
                        $desc .= sprintf("Banner for %s bigger than 2mb (size is %.2fMb)\n", $contestName, $filesize / 1024 / 1024);
                    } else {
                        [$width, $height, $ratio] = Utils::getImageSize($bannerpath);
                        if (mime_content_type($bannerpath) !== 'image/svg+xml' && $width > 1920) {
                            $result = 'W';
                            $desc .= sprintf("Banner for %s is wider than 1920\n", $contestName);
                        } elseif ($ratio < 3 || $ratio > 6) {
                            $result = 'W';
                            $desc .= sprintf("Banner for %s is has a ratio of 1:%.2f, between 1:3 and 1:6 recommended\n", $contestName, $ratio);
                        }
                    }
                }
            }
        }

        $desc = $desc ?: 'Everything OK';

        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'Contest banners',
                'result' => $result,
                'desc' => $desc];
    }

    public function checkProblemsValidate(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        $problems = $this->em->getRepository(Problem::class)->findAll();
        $script_filesize_limit = $this->config->get('script_filesize_limit');
        $output_limit = $this->config->get('output_limit');

        $problemerrors = $moreproblemerrors = [];
        $result = 'O';
        foreach ($problems as $problem) {
            $probid = $problem->getProbid();
            $errors = $this->validator->validate($problem);
            if (count($errors)) {
                $result = 'E';
            }
            $problemerrors[$probid] = $errors;

            $moreproblemerrors[$probid] = '';
            if ($special_compare = $problem->getCompareExecutable()) {
                $exec = $this->em->getRepository(Executable::class)->findOneBy(['execid' => $special_compare->getExecid()]);
                if (!$exec) {
                    $result = 'E';
                    $moreproblemerrors[$probid] .= sprintf("Special compare script %s not found for p%s\n", $special_compare->getExecid(), $probid);
                } elseif ($exec->getType() !== "compare") {
                    $result = 'E';
                    $moreproblemerrors[$probid] .= sprintf("Special compare script %s exists but is of wrong type (%s instead of compare) for p%s\n", $special_compare, $exec->getType(), $probid);
                }
            }
            if ($special_run = $problem->getRunExecutable()) {
                $exec = $this->em->getRepository(Executable::class)->findOneBy(['execid' => $special_run->getExecid()]);
                if (!$exec) {
                    $result = 'E';
                    $moreproblemerrors[$probid] .= sprintf("Special run script %s not found for p%s\n", $special_run->getExecid(), $probid);
                } elseif ($exec->getType() !== "run") {
                    $result = 'E';
                    $moreproblemerrors[$probid] .= sprintf("Special run script %s exists but is of wrong type (%s instead of run) for p%s\n", $special_run, $exec->getType(), $probid);
                }
            }

            $memlimit = $problem->getMemlimit();
            if ($memlimit !== null && $memlimit > $script_filesize_limit) {
                $result = 'E';
                $moreproblemerrors[$probid] .= sprintf("problem-specific memory limit %s is larger than global script filesize limit (%s).\n", $memlimit, $script_filesize_limit);
            }

            $tcs_size = $this->em->createQueryBuilder()
                ->select('tc.testcaseid', 'tc.ranknumber', 'length(tcc.output) as output_size' )
                ->from(Testcase::class, 'tc')
                ->join('tc.content', 'tcc')
                ->andWhere('tc.problem = :probid')
                ->setParameter('probid', $probid)
                ->getQuery()
                ->getResult();
            if (count($tcs_size) === 0) {
                $result = 'E';
                $moreproblemerrors[$probid] .= sprintf("No testcases for p%s\n", $probid);
            } else {
                $problem_output_limit = 1024 * ($problem->getOutputLimit() ?: $output_limit);
                foreach ($tcs_size as $row) {
                    if ($row['output_size'] > $problem_output_limit) {
                        $result = 'E';
                        $moreproblemerrors[$probid] .= sprintf(
                            "Testcase %s for p%s exceeds output limit of %s\n",
                            $row['rank'], $probid, $problem_output_limit
                        );
                    }
                }
            }
        }

        $desc = '';
        foreach ($problemerrors as $probid => $errors) {
            $desc .= "Problem p$probid: ";
            if (count($errors) > 0 || !empty($moreproblemerrors[$probid])) {
                $desc .= (string)$errors . " " .
                    $moreproblemerrors[$probid] . "\n";
            } else {
                $desc .= "OK\n";
            }
        }

        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'Problems validation',
            'result' => $result,
            'desc' => "Validated all problems:\n\n" .
                    ($desc ?: 'No problems with problems found.')];
    }

    public function checkLanguagesValidate(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        $languages = $this->em->getRepository(Language::class)->findAll();

        $languageerrors = $morelanguageerrors = [];
        $result = 'O';
        foreach ($languages as $language) {
            $langid = $language->getLangid();
            $errors = $this->validator->validate($language);
            if (count($errors)) {
                $result = 'E';
            }
            $languageerrors[$langid] = $errors;

            $morelanguageerrors[$langid] = '';
            $compileExecutable = $language->getCompileExecutable();
            if (null === $compileExecutable) {
                $result = 'E';
                $morelanguageerrors[$langid] .= sprintf("No compile script found for %s\n", $langid);
            } elseif ($compile = $language->getCompileExecutable()->getExecid()) {
                $exec = $this->em->getRepository(Executable::class)->findOneBy(['execid' => $compile]);
                if (!$exec) {
                    $result = 'E';
                    $morelanguageerrors[$langid] .= sprintf("Compile script %s not found for %s\n", $compile, $langid);
                } elseif ($exec->getType() !== "compile") {
                    $result = 'E';
                    $morelanguageerrors[$langid] .= sprintf("Compile script %s exists but is of wrong type (%s instead of compile) for %s\n", $compile, $exec->getType(), $langid);
                }
            }
        }

        $desc = '';
        foreach ($languageerrors as $langid => $errors) {
            $desc .= "Language $langid: ";
            if (count($errors) > 0 || !empty($morelanguageerrors[$langid])) {
                $desc .= (string)$errors . " " .
                    $morelanguageerrors[$langid] . "\n";
            } else {
                $desc .= "OK\n";
            }
        }

        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'Languages validation',
            'result' => $result,
            'desc' => "Validated all languages:\n\n" .
                    ($desc ?: 'No languages with problems found.')];
    }

    public function checkTeamPhotos(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        /** @var Team[] $teams */
        $teams = $this->em->getRepository(Team::class)->findAll();

        $desc = '';
        $result = 'O';
        foreach ($teams as $team) {
            if ($tid = $team->getApiId($this->eventLogService)) {
                $photopath = $this->dj->assetPath($tid, 'team', true);
                if ($photopath && ($filesize = filesize($photopath)) > 5 * 1024 * 1024) {
                    $result = 'W';
                    $desc .= sprintf("Photo for t%d (%s) bigger than 5mb (size is %.2fMb)\n", $team->getTeamid(), $team->getName(), $filesize / 1024 / 1024);
                }
            }
        }

        $desc = $desc ?: 'Everything OK';

        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'Team photos',
                'result' => $result,
                'desc' => $desc];
    }

    public function checkAffiliations(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        $show_logos = $this->config->get('show_affiliation_logos');

        if (!$show_logos) {
            $this->stopwatch->stop(__FUNCTION__);
            return ['caption' => 'Team affiliations',
                'result' => 'O',
                'desc' => 'Affiliations display disabled, skipping checks'];
        }

        /** @var TeamAffiliation[] $affils */
        $affils = $this->em->getRepository(TeamAffiliation::class)->findAll();

        $result = 'O';
        $desc = '';
        foreach ($affils as $affiliation) {
            // Only check affiliations that are used, i.e. where there is at least one team.
            if (count($affiliation->getTeams()) === 0) {
                continue;
            }

            if ($aid = $affiliation->getApiId($this->eventLogService)) {
                $logopath = $this->dj->assetPath($aid, 'affiliation', true);
                $logopathMask = str_replace('.jpg', '.{jpg,png,svg}', $this->dj->assetPath($aid, 'affiliation', true, 'jpg'));
                if (!$logopath) {
                    $result = 'W';
                    $desc   .= sprintf("Logo for %s does not exist (looking for %s)\n", $affiliation->getShortname(), $logopathMask);
                } elseif (!is_readable($logopath)) {
                    $result = 'W';
                    $desc .= sprintf("Logo for %s not readable (looking for %s)\n", $affiliation->getShortname(), $logopathMask);
                } elseif (($filesize = filesize($logopath)) > 500 * 1024) {
                    $result = 'W';
                    $desc .= sprintf("Logo for %s bigger than 500Kb (size is %.2fKb)\n", $affiliation->getShortname(), $filesize / 1024);
                } else {
                    [$width, $height, $ratio] = Utils::getImageSize($logopath);
                    if (mime_content_type($logopath) === 'image/svg+xml') {
                        // For SVG's we check the ratio
                        $result = 'W';
                        $desc   .= sprintf("Logo for %s has a ratio of 1:%.2f, should be 1:1\n", $affiliation->getShortname(), $ratio);
                    } elseif ($width !== 64 || $height !== 64) {
                        // For other images we check the size
                        $result = 'W';
                        $desc   .= sprintf("Logo for %s is not 64x64\n", $affiliation->getShortname());
                    }
                }
            }
        }
        $desc = $desc ?: 'Everything OK';

        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'Team affiliations',
            'result' => $result,
            'desc' => $desc];
    }

    public function checkTeamDuplicateNames(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        $teams = $this->em->getRepository(Team::class)->findAll();

        $result = 'O';
        $desc = '';
        $seen = [];
        foreach ($teams as $team) {
            $seen[$team->getEffectiveName()][] = $team->getTeamid();
        }
        foreach ($seen as $teamname => $teams) {
            if (count($teams) > 1) {
                $result = 'W';
                $desc .= sprintf("Team name '%s' in use by multiple teams: %s",
                         $teamname, implode(',', $teams) . "\n");
            }
        }
        $desc = $desc ?: 'Every team name is unique';

        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'Team name uniqueness',
            'result' => $result,
            'desc' => $desc];
    }

    public function checkSelfRegistration(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        $result = 'O';
        $desc = '';

        $selfRegistrationCategories = $this->em->getRepository(TeamCategory::class)->findBy(
            ['allow_self_registration' => 1],
            ['sortorder' => 'ASC']
        );
        if (count($selfRegistrationCategories) === 0) {
            $desc .= "Self-registration is disabled.\n";
        } else {
            $desc .= "Self-registration is enabled.\n";
            if (count($selfRegistrationCategories) === 1) {
                $desc .= sprintf("Team category for self-registered teams: %s.\n",
                    $selfRegistrationCategories[0]->getName());
            } else {
                $desc .= sprintf("Team categories allowed for self-registered teams: %s.\n",
                    implode(', ', array_map(function ($category) {
                        return $category->getName();
                    }, $selfRegistrationCategories)));
            }
        }

        $this->stopwatch->stop(__FUNCTION__);
        return ['caption' => 'Self-registration',
            'result' => $result,
            'desc' => $desc];
    }

    public function checkAllExternalIdentifiers(): array
    {
        $this->stopwatch->start(__FUNCTION__);
        // Get all entity classes.
        $dir   = realpath(sprintf('%s/src/Entity', $this->dj->getDomjudgeWebappDir()));
        $files = glob($dir . '/*.php');

        $result = [];

        foreach ($files as $file) {
            $parts      = explode('/', $file);
            $shortClass = str_replace('.php', '', $parts[count($parts) - 1]);
            $class      = sprintf('App\\Entity\\%s', $shortClass);
            try {
                if (class_exists($class)
                    // ContestProblem is checked using Problem.
                    && $class != ContestProblem::class
                    && ($externalIdField = $this->eventLogService->externalIdFieldForEntity($class))) {
                    $result[$shortClass] = $this->checkExternalIdentifiers($class, $externalIdField);
                }
            } catch (BadMethodCallException $e) {
                // Ignore, this entity does not have an API endpoint.
            }
        }

        $this->stopwatch->stop(__FUNCTION__);
        return $result;
    }

    protected function checkExternalIdentifiers(string $class, string $externalIdField): array
    {
        $this->stopwatch->start(__FUNCTION__);
        $parts      = explode('\\', $class);
        $entityType = $parts[count($parts) - 1];
        $result     = 'O';

        $rowsWithoutExternalId = $this->em->createQueryBuilder()
            ->from($class, 'e')
            ->select('e')
            ->andWhere(sprintf('e.%s IS NULL or e.%s = :empty', $externalIdField, $externalIdField))
            ->setParameter('empty', '')
            ->getQuery()
            ->getResult();

        $inflector = InflectorFactory::create()->build();

        if (!empty($rowsWithoutExternalId)) {
            $result      = 'E';
            $description = '';
            $metadata    = $this->em->getClassMetadata($class);
            foreach ($rowsWithoutExternalId as $entity) {
                $route       = sprintf('jury_%s', $inflector->tableize($entityType));
                $routeParams = [];
                foreach ($metadata->getIdentifierColumnNames() as $column) {
                    // By default, the ID param is the same as the column but then with Id instead of id.
                    $param = str_replace('id', 'Id', $column);
                    if ($param === 'cId') {
                        // For contests we use contestId instead of cId.
                        $param = 'contestId';
                    } elseif ($param === 'clarId') {
                        // For clarifications it is id instead of clarId.
                        $param = 'id';
                    }
                    $getter              = sprintf('get%s', ucfirst($column));
                    $routeParams[$param] = $entity->{$getter}();
                }
                $description .= sprintf("<a href=\"%s\">%s %s</a> does not have an external ID\n",
                                        $this->router->generate($route, $routeParams),
                                        ucfirst(str_replace('_', ' ', $inflector->tableize($entityType))),
                                        Utils::specialchars(implode(', ', $metadata->getIdentifierValues($entity)))
                );
            }
        } else {
            $description = 'All entities OK';
        }

        $this->stopwatch->stop(__FUNCTION__);
        return [
            'caption' => ucfirst($inflector->pluralize(str_replace('_', ' ', $inflector->tableize($entityType)))),
            'result' => $result,
            'desc' => $description,
            'escape' => false,
        ];
    }

    public function getStopwatch(): Stopwatch
    {
        return $this->stopwatch;
    }
}
