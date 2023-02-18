<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\BaseApiEntity;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\ExternalJudgement;
use App\Entity\ExternalSourceWarning;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\Language;
use App\Entity\Submission;
use App\Entity\SubmissionFile;
use App\Entity\Testcase;
use App\Service\AwardService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use SebastianBergmann\Diff\Differ;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension implements GlobalsInterface
{
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected Environment $twig;
    protected EntityManagerInterface $em;
    protected SubmissionService $submissionService;
    protected EventLogService $eventLogService;
    protected AwardService $awards;
    protected TokenStorageInterface $tokenStorage;
    protected AuthorizationCheckerInterface $authorizationChecker;
    protected string $projectDir;

    public function __construct(
        DOMJudgeService               $dj,
        ConfigurationService          $config,
        Environment                   $twig,
        EntityManagerInterface        $em,
        SubmissionService             $submissionService,
        EventLogService               $eventLogService,
        AwardService                  $awards,
        TokenStorageInterface         $tokenStorage,
        AuthorizationCheckerInterface $authorizationChecker,
        string                        $projectDir
    ) {
        $this->dj                   = $dj;
        $this->config               = $config;
        $this->twig                 = $twig;
        $this->em                   = $em;
        $this->submissionService    = $submissionService;
        $this->eventLogService      = $eventLogService;
        $this->awards               = $awards;
        $this->tokenStorage         = $tokenStorage;
        $this->authorizationChecker = $authorizationChecker;
        $this->projectDir           = $projectDir;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('button', [$this, 'button'], ['is_safe' => ['html']]),
            new TwigFunction('calculatePenaltyTime', [$this, 'calculatePenaltyTime']),
            new TwigFunction('showExternalId', [$this, 'showExternalId']),
            new TwigFunction('customAssetFiles', [$this, 'customAssetFiles']),
            new TwigFunction('globalBannerAssetPath', [$this->dj, 'globalBannerAssetPath']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('printtimediff', [$this, 'printtimediff']),
            new TwigFilter('printremainingminutes', [$this, 'printremainingminutes']),
            new TwigFilter('printtime', [$this, 'printtime']),
            new TwigFilter('printtimeHover', [$this, 'printtimeHover'], ['is_safe' => ['html']]),
            new TwigFilter('printResult', [$this, 'printResult'], ['is_safe' => ['html']]),
            new TwigFilter('printValidJuryResult', [$this, 'printValidJuryResult'], ['is_safe' => ['html']]),
            new TwigFilter('printValidJurySubmissionResult', [$this, 'printValidJurySubmissionResult'],
                           ['is_safe' => ['html']]),
            new TwigFilter('printHost', [$this, 'printHost'], ['is_safe' => ['html']]),
            new TwigFilter('printHosts', [$this, 'printHosts'], ['is_safe' => ['html']]),
            new TwigFilter('printFiles', [$this, 'printFiles'], ['is_safe' => ['html']]),
            new TwigFilter('printLazyMode', [$this, 'printLazyMode']),
            new TwigFilter('printYesNo', [$this, 'printYesNo']),
            new TwigFilter('printSize', [Utils::class, 'printSize'], ['is_safe' => ['html']]),
            new TwigFilter('testcaseResults', [$this, 'testcaseResults'], ['is_safe' => ['html']]),
            new TwigFilter('displayTestcaseResults', [$this, 'displayTestcaseResults'],
                           ['is_safe' => ['html']]),
            new TwigFilter('externalCcsUrl', [$this, 'externalCcsUrl']),
            new TwigFilter('lineCount', [$this, 'lineCount']),
            new TwigFilter('base64', 'base64_encode'),
            new TwigFilter('base64_decode', 'base64_decode'),
            new TwigFilter('parseRunDiff', [$this, 'parseRunDiff'], ['is_safe' => ['html']]),
            new TwigFilter('runDiff', [$this, 'runDiff'], ['is_safe' => ['html']]),
            new TwigFilter('interactiveLog', [$this, 'interactiveLog'], ['is_safe' => ['html']]),
            new TwigFilter('codeEditor', [$this, 'codeEditor'], ['is_safe' => ['html']]),
            new TwigFilter('showDiff', [$this, 'showDiff'], ['is_safe' => ['html']]),
            new TwigFilter('printContestStart', [$this, 'printContestStart']),
            new TwigFilter('assetPath', [$this->dj, 'assetPath']),
            new TwigFilter('printTimeRelative', [$this, 'printTimeRelative']),
            new TwigFilter('scoreTime', [$this, 'scoreTime']),
            new TwigFilter('statusClass', [$this, 'statusClass']),
            new TwigFilter('statusIcon', [$this, 'statusIcon'], ['is_safe' => ['html']]),
            new TwigFilter('countryFlag', [$this, 'countryFlag'], ['is_safe' => ['html']]),
            new TwigFilter('affiliationLogo', [$this, 'affiliationLogo'], ['is_safe' => ['html']]),
            new TwigFilter('descriptionExpand', [$this, 'descriptionExpand'], ['is_safe' => ['html']]),
            new TwigFilter('wrapUnquoted', [$this, 'wrapUnquoted']),
            new TwigFilter('hexColorToRGBA', [$this, 'hexColorToRGBA']),
            new TwigFilter('tsvField', [$this, 'toTsvField']),
            new TwigFilter('fileTypeIcon', [$this, 'fileTypeIcon']),
            new TwigFilter('problemBadge', [$this, 'problemBadge'], ['is_safe' => ['html']]),
            new TwigFilter('printMetadata', [$this, 'printMetadata'], ['is_safe' => ['html']]),
            new TwigFilter('printWarningContent', [$this, 'printWarningContent'], ['is_safe' => ['html']]),
            new TwigFilter('entityIdBadge', [$this, 'entityIdBadge'], ['is_safe' => ['html']]),
            new TwigFilter('medalType', [$this->awards, 'medalType']),
        ];
    }

    public function getGlobals(): array
    {
        $refresh_cookie = $this->dj->getCookie("domjudge_refresh");
        $refresh_flag   = ($refresh_cookie == null || (bool)$refresh_cookie);

        $user = $this->dj->getUser();
        $team = $user ? $user->getTeam() : null;

        // These variables mostly exist for the header template.
        return [
            'current_contest_id'            => $this->dj->getCurrentContestCookie(),
            'current_contest'               => $this->dj->getCurrentContest(),
            'current_contests'              => $this->dj->getCurrentContests(),
            'current_public_contest'        => $this->dj->getCurrentContest(-1),
            'current_public_contests'       => $this->dj->getCurrentContests(-1),
            'have_printing'                 => $this->config->get('print_command'),
            'refresh_flag'                  => $refresh_flag,
            'icat_url'                      => $this->config->get('icat_url'),
            'external_ccs_submission_url'   => $this->config->get('external_ccs_submission_url'),
            'current_team_contest'          => $team ? $this->dj->getCurrentContest($team->getTeamid()) : null,
            'current_team_contests'         => $team ? $this->dj->getCurrentContests($team->getTeamid()) : null,
            'submission_languages'          => $this->em->createQueryBuilder()
                                                        ->from(Language::class, 'l')
                                                        ->select('l')
                                                        ->andWhere('l.allowSubmit = 1')
                                                        ->getQuery()
                                                        ->getResult(),
            'alpha3_countries'              => Countries::getAlpha3Names(),
            'alpha3_alpha2_country_mapping' => array_combine(
                Countries::getAlpha3Codes(),
                array_map(fn($alpha3) => Countries::getAlpha2Code($alpha3), Countries::getAlpha3Codes())
            ),
            'show_shadow_differences'       => $this->tokenStorage->getToken() &&
                                               $this->authorizationChecker->isGranted('ROLE_ADMIN') &&
                                               $this->config->get('data_source') === DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL,
            'doc_links'                     => $this->dj->getDocLinks(),
        ];
    }

    public function printtimediff(float $start, ?float $end = null): string
    {
        return Utils::printtimediff($start, $end);
    }

    public function printremainingminutes(float $start, float $end): string
    {
        $minutesRemaining = floor(($end - $start)/60);
        if ($minutesRemaining < 1) {
            return 'less than 1 minute to go';
        } elseif ($minutesRemaining == 1) {
            return '1 minute to go';
        } else {
            return $minutesRemaining . ' minutes to go';
        }
    }

    /**
     * Print a time formatted as specified. The format is according to date().
     * @param string|float $datetime
     * @param Contest|null $contest If given, print time relative to that contest start.
     */
    public function printtime($datetime, ?string $format = null, ?Contest $contest = null): string
    {
        if ($datetime === null) {
            $datetime = Utils::now();
        }
        if ($contest !== null && $this->config->get('show_relative_time')) {
            $relativeTime = $contest->getContestTime((float)$datetime);
            $sign         = ($relativeTime < 0 ? -1 : 1);
            $relativeTime *= $sign;
            // We're not showing seconds, while the last minute before
            // contest start should show as "-0:01", so if there's a
            // nonzero amount of seconds before the contest, we have to
            // add a minute.
            $s            = $relativeTime % 60;
            $relativeTime = ($relativeTime - $s) / 60;
            if ($sign < 0 && $s > 0) {
                $relativeTime++;
            }
            $m            = $relativeTime % 60;
            $relativeTime = ($relativeTime - $m) / 60;
            $h            = $relativeTime;
            if ($sign < 0) {
                return sprintf("-%d:%02d", $h, $m);
            } else {
                return sprintf("%d:%02d", $h, $m);
            }
        } else {
            if ($format === null) {
                $format = $this->config->get('time_format');
            }
            return Utils::printtime($datetime, $format);
        }
    }

    /**
     * Helper function to print a time in the default/configured format,
     * and a hover title attribute with the full datetime string.
     *
     * @param string|float $datetime
     * @param Contest|null $contest If given, print time relative to that contest start.
     */
    public function printtimeHover($datetime, ?Contest $contest = null): string
    {
        if ($datetime === null) {
            $datetime = Utils::now();
        }
        return '<span title="' .
               Utils::printtime($datetime, 'Y-m-d H:i:s (T)') . '">' .
               $this->printtime($datetime, null, $contest) .
               '</span>';
    }

    public static function printLazyMode(?int $val): string
    {
        switch ($val) {
            case false:
                return "-";
            case DOMJudgeService::EVAL_DEMAND:
                return "On demand";
            case DOMJudgeService::EVAL_FULL:
                return "No";
            case DOMJudgeService::EVAL_LAZY:
                return "Yes";
            default:
                return "Unknown mode $val";
        }
    }

    public static function printYesNo(bool $val): string
    {
        return $val ? 'Yes' : 'No';
    }

    public function button(
        string  $url,
        string  $text,
        string  $type = 'primary',
        ?string $icon = null,
        bool    $isAjaxModal = false
    ): string {
        if ($icon) {
            $icon = sprintf('<i class="fas fa-%s"></i>&nbsp;', $icon);
        }

        if ($isAjaxModal) {
            return sprintf('<a href="%s" class="btn btn-%s" title="%s" data-ajax-modal>%s%s</a>', $url, $type, $text,
                           $icon, $text);
        } else {
            return sprintf('<a href="%s" class="btn btn-%s" title="%s">%s%s</a>', $url, $type, $text, $icon, $text);
        }
    }

    public static function statusClass(string $status): string
    {
        switch ($status) {
            case 'noconn':
                return 'text-muted';
            case 'crit':
                return 'text-danger';
            case 'warn':
                return 'text-warning';
            case 'ok':
                return 'text-success';
        }
        return '';
    }

    public static function statusIcon(string $status): string
    {
        switch ($status) {
            case 'noconn':
                $icon = 'question';
                break;
            case 'crit':
                $icon = 'times';
                break;
            case 'warn':
                $icon = 'exclamation';
                break;
            case 'ok':
                $icon = 'check';
                break;
            default:
                return 'unknown';
        }
        return sprintf('<i class="fas fa-%s-circle" aria-hidden="true"></i><span class="sr-only">%s</span>', $icon,
                       $status);
    }

    public function countryFlag(?string $alpha3CountryCode, bool $showFullname = false): string
    {
        if (empty($alpha3CountryCode)) {
            return '';
        }

        try {
            $countryAlpha2 = strtolower(Countries::getAlpha2Code($alpha3CountryCode));
        } catch (MissingResourceException $e) {
            return '';
        }
        $assetFunction  = $this->twig->getFunction('asset')->getCallable();
        $countryFlagUrl = call_user_func($assetFunction, sprintf('flags/4x3/%s.svg', $countryAlpha2));

        $countryName    = Countries::getAlpha3Name($alpha3CountryCode);

        if ($showFullname) {
            return sprintf('<img src="%s" alt="" class="countryflag"> %s',
                           $countryFlagUrl, $countryName);
        }
        return sprintf('<img loading="lazy" src="%s" alt="%s" title="%s" class="countryflag">',
           $countryFlagUrl, $alpha3CountryCode, $countryName);
    }

    public function affiliationLogo(string $affiliationId, string $shortName): string
    {
        if ($asset = $this->dj->assetPath($affiliationId, 'affiliation')) {
            $assetFunction = $this->twig->getFunction('asset')->getCallable();
            $assetUrl      = call_user_func($assetFunction, $asset);
            return sprintf('<img src="%s" alt="%s" class="affiliation-logo">',
                           Utils::specialchars($assetUrl), Utils::specialchars($shortName));
        }

        return '';
    }

    public function testcaseResults(Submission $submission, ?bool $showExternal = false): string
    {
        // We use a direct SQL query here for performance reasons
        if ($showExternal) {
            /** @var ExternalJudgement|null $externalJudgement */
            $externalJudgement   = $submission->getExternalJudgements()->first();
            $externalJudgementId = $externalJudgement ? $externalJudgement->getExtjudgementid() : null;
            $probId              = $submission->getProblem()->getProbid();
            $testcases           = $this->em->getConnection()->fetchAllAssociative(
                'SELECT er.result as runresult, t.ranknumber, t.description
                  FROM testcase t
                  LEFT JOIN external_run er ON (er.testcaseid = t.testcaseid
                                              AND er.extjudgementid = :extjudgementid)
                  WHERE t.probid = :probid ORDER BY ranknumber',
                ['extjudgementid' => $externalJudgementId, 'probid' => $probId]);

            $submissionDone = $externalJudgement && !empty($externalJudgement->getEndtime());
        } else {
            /** @var Judging|null $judging */
            $judging   = $submission->getJudgings()->first();
            $judgingId = $judging ? $judging->getJudgingid() : null;
            $probId    = $submission->getProblem()->getProbid();
            $testcases = $this->em->getConnection()->fetchAllAssociative(
                'SELECT r.runresult, jh.hostname, jt.valid, t.ranknumber, t.description
                  FROM testcase t
                  LEFT JOIN judging_run r ON (r.testcaseid = t.testcaseid
                                              AND r.judgingid = :judgingid)
                  LEFT JOIN judgetask jt ON (r.judgetaskid = jt.judgetaskid)
                  LEFT JOIN judgehost jh on (jt.judgehostid = jh.judgehostid)
                  WHERE t.probid = :probid ORDER BY ranknumber',
                ['judgingid' => $judgingId, 'probid' => $probId]);

            $submissionDone = $judging && !empty($judging->getEndtime());
        }

        $results = '';
        foreach ($testcases as $key => $testcase) {
            $class = $submissionDone ? 'secondary' : 'primary';
            $text  = '?';

            if ($testcase['runresult'] !== null) {
                $text  = substr($testcase['runresult'], 0, 1);
                $class = 'danger';
                if ($testcase['runresult'] === Judging::RESULT_CORRECT) {
                    $text  = '✓';
                    $class = 'success';
                }
            } elseif (array_key_exists('valid', $testcase) && !$testcase['valid']) {
                $text = '✕';
            } elseif (array_key_exists('hostname', $testcase) && $testcase['hostname'] !== null) {
                $text  = '↺';
                $class = 'info';
            }

            if (!empty($testcase['description'])) {
                $title = sprintf('Run %d: %s', $key + 1,
                                 Utils::specialchars($testcase['description']));
            } else {
                $title = sprintf('Run %d', $key + 1);
            }

            $results .= sprintf('<span class="badge badge-%s badge-testcase" title="%s">%s</span>', $class, $title,
                                $text);
        }

        return $results;
    }

    // TODO: this function shares a lot with the above one, unify them?
    public function displayTestcaseResults(array $testcases, bool $submissionDone, bool $isExternal = false): string
    {
        /** @var Testcase[] $testcases */
        $results = '';
        $lastTypeSample = true;
        foreach ($testcases as $testcase) {
            if ($testcase->getSample() != $lastTypeSample) {
                $results        .= ' | ';
                $lastTypeSample = $testcase->getSample();
            }

            $class     = $submissionDone ? 'secondary' : 'primary';
            $text      = '?';
            $isCorrect = false;
            /** @var JudgingRun $run */
            $run = $isExternal ? $testcase->getFirstExternalRun() : $testcase->getFirstJudgingRun();
            if ($isExternal) {
                $runResult = $run ? $run->getResult() : null;
            } else {
                $runResult = $run ? $run->getRunresult() : null;
            }

            if ($run) {
                if ($runResult !== null) {
                    $text  = substr($runResult, 0, 1);
                    $class = 'danger';
                    if ($runResult === Judging::RESULT_CORRECT) {
                        $isCorrect = true;
                        $text      = '✓';
                        $class     = 'success';
                    }
                } elseif ($run->getJudgeTask()->getJudgehost() !== null) {
                    $text  = '↺';
                    $class = 'info';
                }
            }

            $titleElements = ["#" . $testcase->getRank()];
            if (!empty($testcase->getOrigInputFilename())) {
                $titleElements[] = "name: " . $testcase->getOrigInputFilename();
            }

            $description = $testcase->getDescription(true);
            if (!empty($description)) {
                $titleElements[] = "desc: " . $description;
            }

            if ($run && $runResult !== null) {
                $titleElements[] = sprintf('runtime: %ss', $run->getRuntime());
                $titleElements[] = sprintf('result: %s', $runResult);
            }
            $icon    = sprintf('<span class="badge badge-%s badge-testcase">%s</span>', $class, $text);
            $results .= sprintf('<a title="%s" href="#run-%d" %s>%s</a>',
                                join(', ', $titleElements), $testcase->getRank(),
                                $isCorrect ? 'onclick="display_correctruns(true);"' : '', $icon);
        }

        return $results;
    }

    public function printResult(?string $result, bool $valid = true, bool $jury = false): string
    {
        switch ($result) {
            case 'too-late':
                $style = 'sol_queued';
                break;
            /** @noinspection PhpMissingBreakStatementInspection */
            case '':
                $result = 'judging';
            // no break
            case 'judging':
            case 'queued':
            case 'pending':
            case 'aborted':
            case 'n / a':
                if (!$jury) {
                    $result = 'pending';
                }
                $style = 'sol_queued';
                break;
            case 'correct':
                $style = 'sol_correct';
                break;
            default:
                $style = 'sol_incorrect';
        }

        return sprintf('<span class="sol %s">%s</span>', $valid ? $style : 'disabled', $result);
    }

    public function printValidJuryResult(?string $result): string
    {
        return $this->printResult($result, true, true);
    }

    public function printValidJurySubmissionResult(Submission $submission, bool $forDisplay = true): string
    {
        /** @var Judging|null $firstJudging */
        $firstJudging  = $submission->getJudgings()->first();
        $judgingResult = '';
        if ($firstJudging) {
            $judgingResult = $forDisplay
                ? $this->printValidJuryResult($firstJudging->getResult())
                : $firstJudging->getResult();
        }
        $output = $forDisplay ? '' : 'queued';
        if ($submission->getSubmittime() > $submission->getContest()->getEndtime()) {
            if ($forDisplay) {
                $output .= $this->printValidJuryResult('too-late');
            }
            if ($firstJudging && $firstJudging->getResult()) {
                if ($forDisplay) {
                    $output .= ' (' . $judgingResult . ')';
                } else {
                    $output = $judgingResult;
                }
            }
        } elseif (!$firstJudging || !$firstJudging->getResult()) {
            if ($firstJudging && $firstJudging->isStarted()) {
                $output = $forDisplay ? $this->printValidJuryResult('') : 'judging';
            } else {
                $output = $forDisplay ? $this->printValidJuryResult('queued') : 'queued';
            }
        } else {
            $output = $judgingResult;
        }

        if ($forDisplay && $submission->isStillBusy()) {
            $output .= ' (&hellip;)';
        }

        return $output;
    }

    public function externalCcsUrl(Submission $submission): ?string
    {
        $extCcsUrl = $this->config->get('external_ccs_submission_url');
        if (!empty($extCcsUrl)) {
            $dataSource = $this->config->get('data_source');
            if ($dataSource == 2 && $submission->getExternalid()) {
                return str_replace(['[contest]', '[id]'],
                                   [$submission->getContest()->getExternalid(), $submission->getExternalid()],
                                   $extCcsUrl);
            } elseif ($dataSource == 1) {
                return str_replace(['[contest]', '[id]'],
                                   [$submission->getContest()->getExternalid(), $submission->getSubmitid()],
                                   $extCcsUrl);
            }
        }

        return null;
    }

    /**
     * Prints the first file (and potentially the number of additional files).
     */
    public function printFiles(Collection $files): string
    {
        $files = $files->toArray();
        if (empty($files)) {
            // Should not happen, but better do something reasonable here if it would.
            return "source code";
        }
        $firstFile = $files[0]->getFilename();
        if (count($files) == 1) {
            return sprintf('<span class="hostname">%s</span>', Utils::specialchars($firstFile));
        }
        return sprintf('<span class="filename">%s</span> (and %d more)',
                       Utils::specialchars($firstFile),
                       count($files) - 1
        );
    }

    /**
     * Formats a given hostname. If $full = true, then the full hostname will be printed,
     * else only the local part (for keeping tables readable)
     */
    public function printHost(?string $hostname, bool $full = false): string
    {
        if ($hostname === null) {
            return '<span class="nodata">hostname unset</span>';
        }
        // Shorten the hostname to first label, but not if it's an IP address.
        if (!$full && !preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $hostname)) {
            $expl     = explode('.', $hostname);
            $hostname = array_shift($expl);
        }

        return sprintf('<span class="hostname">%s</span>', Utils::specialchars($hostname));
    }

    /**
     * Extract the longest common prefix of all the provided strings.
     */
    private function getCommonPrefix(array $strings): string
    {
        $common_prefix = $strings[0];
        foreach ($strings as $string) {
            $len = strlen($string);
            while ($len > 0) {
                if (substr_compare($common_prefix, $string, 0, $len) == 0) {
                    break;
                }
                $len--;
            }
            if ($len == 0) {
                $common_prefix = "";
                break;
            }
            $common_prefix = substr($common_prefix, 0, $len);
        }
        return $common_prefix;
    }

    /**
     * Formats a list of given hostnames, extracting a common prefix and suffix.
     */
    public function printHosts(array $hostnames): string
    {
        if (empty($hostnames)) {
            return "";
        }
        if (count($hostnames) == 1) {
            return $this->printHost($hostnames[0]);
        }
        $hostnames = array_unique($hostnames);

        $local_parts = [];
        foreach ($hostnames as $hostname) {
            // Shorten the hostname to first label, but not if it's an IP address.
            if (!preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $hostname)) {
                $expl     = explode('.', $hostname);
                $hostname = array_shift($expl);
            }
            $local_parts[] = $hostname;
        }

        // Extract the longest common prefix.
        $common_prefix = $this->getCommonPrefix($local_parts);
        $prefix_len = strlen($common_prefix);

        // Extract the longest common suffix.
        $reversed = array_map('strrev', $local_parts);
        $common_suffix = strrev($this->getCommonPrefix($reversed));
        $suffix_len = strlen($common_suffix);

        // Extract the list of remaining parts. This list may contain empty values. If $common_prefix overlaps
        // $common_suffix, then $common_prefix = $common_suffix = the entire string.
        $middle_parts = array_map(fn($host) => substr($host, $prefix_len, strlen($host) - $prefix_len - $suffix_len), $local_parts);
        // Usually the middle parts contain numbers, so use natural sort for them.
        usort($middle_parts, 'strnatcmp');

        if (empty($common_prefix) && empty($common_suffix)) {
            // No common prefix nor suffix: list all the names without "{}".
            return implode(", ", array_map([$this, 'printHost'], $hostnames));
        } else {
            $hosts = $common_prefix . "{" . implode(",", $middle_parts) . "}" . $common_suffix;
            return $this->printHost($hosts, true);
        }
    }

    public function lineCount(string $input): int
    {
        return mb_substr_count($input, "\n");
    }

    public function parseRunDiff(string $difftext): string
    {
        $line = strtok($difftext, "\n"); //first line
        if ($line === false || sscanf($line, "### DIFFERENCES FROM LINE %d ###\n", $firstdiff) != 1) {
            return Utils::specialchars($difftext);
        }
        $return = $line . "\n";

        // Add second line 'team ? reference'.
        $line   = strtok("\n");
        $return .= $line . "\n";

        // We determine the line number width from the '_' characters and
        // the separator position from the character '?' on the second line.
        $linenowidth = mb_strrpos($line, '_') + 1;
        $midloc      = mb_strpos($line, '?') - ($linenowidth + 1);

        $line = strtok("\n");
        while (mb_strlen($line) != 0) {
            $linenostr = mb_substr($line, 0, $linenowidth);
            $diffline  = mb_substr($line, $linenowidth + 1);
            $mid       = mb_substr($diffline, $midloc - 1, 3);
            switch ($mid) {
                case ' = ':
                    $formdiffline = "<span class='correct'>" . Utils::specialchars($diffline) . "</span>";
                    break;
                case ' ! ':
                    $formdiffline = "<span class='differ'>" . Utils::specialchars($diffline) . "</span>";
                    break;
                case ' $ ':
                    $formdiffline = "<span class='endline'>" . Utils::specialchars($diffline) . "</span>";
                    break;
                case ' > ':
                case ' < ':
                    $formdiffline = "<span class='extra'>" . Utils::specialchars($diffline) . "</span>";
                    break;
                default:
                    $formdiffline = Utils::specialchars($diffline);
            }
            $return = $return . $linenostr . " " . $formdiffline . "\n";
            $line   = strtok("\n");
        }
        return $return;
    }

    public function interactiveLog(string $log, bool $forTeam = false): string
    {
        $truncated  = '/\[output display truncated after \d* B\]$/';
        $matches    = [];
        $truncation = "";
        if (preg_match($truncated, $log, $matches)) {
            $truncation = $matches[0];
            $log        = preg_replace($truncated, "", $log);
            $log        = rtrim($log);
        }
        if ($forTeam) {
            $header = "<table><tr><th>jury</th><th>your submission<th></tr>\n";
        } else {
            $header = "<table><tr><th>time</th><th>validator</th><th>submission<th></tr>\n";
        }
        $body = "";
        $idx  = 0;
        while ($idx < strlen($log)) {
            $slashPos = strpos($log, "/", $idx);
            if ($slashPos === false) {
                break;
            }
            $time     = substr($log, $idx + 1, $slashPos - $idx - 1);
            $idx      = $slashPos + 1;
            $closePos = strpos($log, "]", $idx);
            if ($closePos === false) {
                break;
            }
            $lenStr = substr($log, $idx, $closePos - $idx);
            $len    = (int)$lenStr;
            $idx    = $closePos + 1;
            if ($idx >= strlen($log)) {
                break;
            }
            $is_validator = $log[$idx] == '>';
            $content      = substr($log, $idx + 3, $len);
            if (empty($content)) {
                break;
            }
            $content   = htmlspecialchars($content);
            $content   = '<td class="output_text">'
                         . str_replace("\n", "\u{21B5}<br/>", $content)
                         . '</td>';
            $idx       += $len + 4;
            $team      = $is_validator ? '<td/>' : $content;
            $validator = $is_validator ? $content : '<td/>';
            $body      .= "<tr>" . ($forTeam ? "" : "<td>$time</td>")
                          . $validator
                          . $team
                          . "</tr>\n";
        }
        return $header . $body . "</table>" . $truncation;
    }

    public function runDiff(array $runOutput): string
    {
        // TODO: can be improved using diffposition.txt
        // FIXME: only show when diffposition.txt is set?
        // FIXME: cut off after XXX lines
        $lines_team = preg_split('/\n/', trim($runOutput['output_run']));
        $lines_ref  = preg_split('/\n/', trim($runOutput['output_reference']));

        $diffs    = [];
        $firstErr = sizeof($lines_team) + 1;
        $lastErr  = -1;
        $n        = min(sizeof($lines_team), sizeof($lines_ref));
        for ($i = 0; $i < $n; $i++) {
            $lcs = Utils::computeLcsDiff($lines_team[$i], $lines_ref[$i]);
            if ($lcs[0] === true) {
                $firstErr = min($firstErr, $i);
                $lastErr  = max($lastErr, $i);
            }
            $diffs[] = $lcs[1];
        }
        $contextLines = 5;
        $firstErr     -= $contextLines;
        $lastErr      += $contextLines;
        $firstErr     = max(0, $firstErr);
        $lastErr      = min(sizeof($diffs) - 1, $lastErr);
        $result       = "<br/>\n<table class=\"lcsdiff output_text\">\n";
        if ($firstErr > 0) {
            $result .= "<tr><td class=\"linenr\">[...]</td><td/></tr>\n";
        }
        for ($i = $firstErr; $i <= $lastErr; $i++) {
            $result .= "<tr><td class=\"linenr\">" . ($i + 1) . "</td><td>" . $diffs[$i] . "</td></tr>";
        }
        if ($lastErr < sizeof($diffs) - 1) {
            $result .= "<tr><td class=\"linenr\">[...]</td><td/></tr>\n";
        }
        $result .= "</table>\n";

        return $result;
    }

    /**
     * Output a (optionally readonly) code editor for the given submission file.
     * @param string|null $language Ace language to use
     * @param bool $editable Whether to allow editing
     * @param string $elementToUpdate HTML element to update when input changes
     * @param string|null $filename If $language is null, filename to use to determine language
     */
    public function codeEditor(
        string  $code,
        string  $index,
        ?string $language = null,
        bool    $editable = false,
        string  $elementToUpdate = '',
        ?string $filename = null
    ): string {
        $editor = <<<HTML
<div class="editor" id="__EDITOR__">%s</div>
<script>
var __EDITOR__ = ace.edit("__EDITOR__");
__EDITOR__.setTheme("ace/theme/eclipse");
__EDITOR__.setOptions({ maxLines: Infinity });
__EDITOR__.setReadOnly(%s);
%s
document.getElementById("__EDITOR__").editor = __EDITOR__;
%s
</script>
HTML;
        $rank   = $index;
        $id     = sprintf('editor%s', $rank);
        $code   = Utils::specialchars($code);
        if ($elementToUpdate) {
            $extraForEdit = <<<JS
__EDITOR__.getSession().on('change', function() {
    var textarea = document.getElementById("$elementToUpdate");
    textarea.value = __EDITOR__.getSession().getValue();
});
JS;
        } else {
            $extraForEdit = '';
        }

        if ($language !== null) {
            $mode = sprintf('__EDITOR__.getSession().setMode("ace/mode/%s");', $language);
        } elseif ($filename !== null) {
            $modeTemplate = <<<JS
var modelist = ace.require('ace/ext/modelist');
var filePath = "%s";
var mode = modelist.getModeForPath(filePath).mode;
__EDITOR__.getSession().setMode(mode);
JS;
            $mode         = sprintf($modeTemplate, Utils::specialchars($filename));
        } else {
            $mode = '';
        }

        return str_replace('__EDITOR__', $id,
                           sprintf($editor, $code, $editable ? 'false' : 'true', $mode, $extraForEdit));
    }

    protected function parseSourceDiff($difftext): string
    {
        $line   = strtok((string)$difftext, "\n"); // first line
        $return = '';
        while ($line !== false && strlen($line) != 0) {
            // Strip any additional DOS/MAC newline characters:
            $line = trim($line, "\r\n");
            switch (substr($line, 0, 1)) {
                case '-':
                    $formdiffline = "<span class='diff-del'>" . Utils::specialchars($line) . "</span>";
                    break;
                case '+':
                    $formdiffline = "<span class='diff-add'>" . Utils::specialchars($line) . "</span>";
                    break;
                default:
                    $formdiffline = Utils::specialchars($line);
            }
            $return .= $formdiffline . "\n";
            $line   = strtok("\n");
        }
        return $return;
    }

    public function showDiff(SubmissionFile $newFile, SubmissionFile $oldFile): string
    {
        $differ = new Differ;
        return $this->parseSourceDiff($differ->diff($oldFile->getSourcecode(), $newFile->getSourcecode()));
    }

    public function printContestStart(Contest $contest): string
    {
        $res = "scheduled to start ";
        if (!$contest->getStarttimeEnabled()) {
            $res = "start delayed, was scheduled ";
        }
        if ($this->printtime(Utils::now(), 'Ymd') == $this->printtime($contest->getStarttime(false), 'Ymd')) {
            // Today
            $res .= "at " . $this->printtime($contest->getStarttime(false));
        } else {
            // Print full date
            $res .= "on " . $this->printtime($contest->getStarttime(false), 'D d M Y H:i:s T');
        }
        return $res;
    }

    public function customAssetFiles(string $type): array
    {
        if (in_array($type, ['css', 'js'])) {
            return $this->dj->getAssetFiles("$type/custom");
        }

        return [];
    }

    /**
     * Print the relative time in h:mm:ss[.uuuuuu] format.
     */
    public function printTimeRelative(float $relativeTime, bool $useMicroseconds = false): string
    {
        $sign         = $relativeTime < 0 ? '-' : '';
        $relativeTime = abs($relativeTime);
        $fracString   = '';

        if ($useMicroseconds) {
            $fracString   = explode('.', sprintf('%.6f', $relativeTime))[1];
            $relativeTime = (int)floor($relativeTime);
        } else {
            // For negative times we still want to floor, but we've
            // already removed the sign, so take ceil() if negative.
            $relativeTime = (int)($sign == '-' ? ceil($relativeTime) : floor($relativeTime));
        }

        $h            = (int)floor($relativeTime / 3600);
        $relativeTime %= 3600;

        $m            = (int)floor($relativeTime / 60);
        $relativeTime %= 60;

        $s = (int)$relativeTime;

        if ($useMicroseconds) {
            $s .= '.' . $fracString;
        }

        return sprintf($sign . '%01d:%02d:%02d' . $fracString, $h, $m, $s);
    }

    /**
     * Display the scoretime for the given time.
     * @param string|float $time
     */
    public function scoreTime($time): int
    {
        return Utils::scoretime($time, (bool)$this->config->get('score_in_seconds'));
    }

    public function calculatePenaltyTime(bool $solved, int $num_submissions): int
    {
        return Utils::calcPenaltyTime($solved, $num_submissions, (int)$this->config->get('penalty_time'),
                                      (bool)$this->config->get('score_in_seconds'));
    }

    /**
     * Print the given description, collapsing it by default if it is too big.
     */
    public function descriptionExpand(?string $description = null): string
    {
        if ($description == null) {
            return '';
        }
        $descriptionLines = explode("\n", $description);
        if (count($descriptionLines) <= 3) {
            return implode('<br>', $descriptionLines);
        } else {
            $default         = implode('<br>', array_slice($descriptionLines, 0, 3));
            $defaultEscaped  = Utils::specialchars($default);
            $expandedEscaped = Utils::specialchars(implode('<br>', $descriptionLines));
            return <<<EOF
<span>
    <span data-expanded="$expandedEscaped" data-collapsed="$defaultEscaped">
    $default
    </span>
    <br/>
    <a href="javascript:;" onclick="toggleExpand(event)">[expand]</a>
</span>
EOF;
        }
    }

    /**
     * @param object|string $entity
     */
    public function showExternalId($entity): bool
    {
        return $this->eventLogService->externalIdFieldForEntity($entity) !== null;
    }

    public function wrapUnquoted(string $text, int $width = 75, string $quote = '>'): string
    {
        return Utils::wrapUnquoted($text, $width, $quote);
    }

    public function hexColorToRGBA(string $text, float $opacity = 1): string
    {
        $col = Utils::convertToHex($text);
        if (is_null($col)) {
            return $text;
        }
        preg_match_all("/[0-9A-Fa-f]{2}/", $col, $m);
        if (!count($m)) {
            return $text;
        }

        $m = current($m);
        switch (count($m)) {
            case 4:
                // We also have opacity; load that and use
                // RGB of case 3
                $opacity = hexdec(array_pop($m));
                // no-break
            case 3:
                $vals   = array_map("hexdec", $m);
                $vals[] = $opacity;

                return "rgba(" . implode(",", $vals) . ")";
        }

        return $text;
    }

    public function toTsvField(string $field): string
    {
        return Utils::toTsvField($field);
    }

    public function fileTypeIcon(string $type): string
    {
        switch ($type) {
            case 'pdf':
                $iconName = 'pdf';
                break;
            case 'txt':
                $iconName = 'alt';
                break;
            default:
                $iconName = 'code';
                break;
        }

        return 'fas fa-file-' . $iconName;
    }

    public function problemBadge(ContestProblem $problem): string
    {
        $rgb        = Utils::convertToHex($problem->getColor() ?? '#ffffff');
        $background = Utils::parseHexColor($rgb);

        // Pick a border that's a bit darker.
        $darker = $background;
        $darker[0] = max($darker[0] - 64, 0);
        $darker[1] = max($darker[1] - 64, 0);
        $darker[2] = max($darker[2] - 64, 0);
        $border    = Utils::rgbToHex($darker);

        // Pick the foreground text color based on the background color.
        $foreground = ($background[0] + $background[1] + $background[2] > 450) ? '#000000' : '#ffffff';
        return sprintf(
            '<span class="badge problem-badge" style="background-color: %s; min-width: 28px; border: 1px solid %s"><span style="color: %s;">%s</span></span>',
            $rgb,
            $border,
            $foreground,
            $problem->getShortname()
        );
    }

    public function printMetadata(?string $metadata): string
    {
        if ($metadata === null) {
            return '';
        }
        $metadata = $this->dj->parseMetadata($metadata);
        return '<span style="display:inline; margin-left: 5px;">'
            . '<i class="fas fa-stopwatch" title="runtime"></i>'
            . $metadata['cpu-time'] . 's'
            . ' CPU, '
            . $metadata['wall-time'] . 's'
            . ' wall time, '
            . '<i class="fas fa-memory" title="RAM"></i>'
            . Utils::printsize((int)($metadata['memory-bytes']))
            . ', '
            . '<i class="fas fa-exitcode" title="runtime"></i>'
            . 'exit-code: ' . $metadata['exitcode'];
    }

    public function printWarningContent(ExternalSourceWarning $warning): string
    {
        switch ($warning->getType()) {
            case ExternalSourceWarning::TYPE_UNSUPORTED_ACTION:
                $action = $warning->getContent()['action'];
                return "Action $action not supported for this entity type";
            case ExternalSourceWarning::TYPE_DATA_MISMATCH:
                $rows = [];
                $null = '&lt;null&gt;';
                foreach ($warning->getContent()['diff'] as $field => $diff) {
                    $tdField    = "<td><code>$field</code></td>";
                    $tdUs       = sprintf(
                        '<td><code>%s</code></td>',
                        $diff['us'] === null ? $null : $diff['us']
                    );
                    $tdExternal = sprintf(
                        '<td><code>%s</code></td>',
                        $diff['external'] === null ? $null : $diff['external']
                    );
                    $rows[]     = "<tr>{$tdField}{$tdUs}{$tdExternal}</tr>";
                }

                $header  = <<<'EOF'
<thead>
<tr>
<th style="width: 20%;">Field</th>
<th style="width: 40%;">Our value</th>
<th style="width: 40%;">External value</th>
</tr>
</thead>
EOF;
                $rowData = implode('', $rows);

                return "<table class='table table-sm table-striped'>$header<tbody>$rowData</tbody></table>";
            case ExternalSourceWarning::TYPE_DEPENDENCY_MISSING:
                $rows = [];
                foreach ($warning->getContent()['dependencies'] as $dependency) {
                    $type   = $dependency['type'];
                    $id     = $dependency['id'];
                    $rows[] = "<tr><td>$type</td><td>$id</td></tr>";
                }
                $header  = <<<'EOF'
<thead>
<tr>
<th style="width: 20%;">Type</th>
<th style="width: 80%;">ID</th>
</tr>
</thead>
EOF;
                $rowData = implode('', $rows);

                return "<table class='table table-sm table-striped'>$header<tbody>$rowData</tbody></table>";
            case ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND:
            case ExternalSourceWarning::TYPE_ENTITY_SHOULD_NOT_EXIST:
                return '';
            case ExternalSourceWarning::TYPE_SUBMISSION_ERROR:
                return $warning->getContent()['message'];
        }

        return '';
    }

    /**
     * Get the entity ID badge to display for the given entity.
     *
     * When we are in a data source mode that uses external ID's, those will be used and the
     * internal ID will be shown in a tooltip.
     *
     * @param string $idPrefix The prefix to use for the internal ID, if any.
     */
    public function entityIdBadge(BaseApiEntity $entity, string $idPrefix = ''): string
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $metadata = $this->em->getClassMetadata(get_class($entity));
        $primaryKeyColumn = $metadata->getIdentifierColumnNames()[0];
        $externalIdField = $this->eventLogService->externalIdFieldForEntity($entity);

        return $this->twig->render('jury/entity_id_badge.html.twig', [
            'idPrefix' => $idPrefix,
            'id' => $propertyAccessor->getValue($entity, $primaryKeyColumn),
            'externalId' => $externalIdField ? $propertyAccessor->getValue($entity, $externalIdField) : null,
        ]);
    }
}
