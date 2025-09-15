<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\BaseApiEntity;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\ExternalJudgement;
use App\Entity\ExternalRun;
use App\Entity\ExternalSourceWarning;
use App\Entity\HasExternalIdInterface;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\SubmissionFile;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Entity\Testcase;
use App\Service\AwardService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\SubmissionService;
use App\Utils\Scoreboard\ScoreboardMatrixItem;
use App\Utils\Scoreboard\TeamScore;
use App\Utils\Utils;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\Runtime\EscaperRuntime;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        protected readonly DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly Environment $twig,
        protected readonly EntityManagerInterface $em,
        protected readonly SubmissionService $submissionService,
        protected readonly EventLogService $eventLogService,
        protected readonly AwardService $awards,
        protected readonly TokenStorageInterface $tokenStorage,
        protected readonly AuthorizationCheckerInterface $authorizationChecker,
        protected readonly RouterInterface $router,
        #[Autowire('%kernel.project_dir%')]
        protected readonly string $projectDir
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('button', $this->button(...), ['is_safe' => ['html']]),
            new TwigFunction('calculatePenaltyTime', $this->calculatePenaltyTime(...)),
            new TwigFunction('customAssetFiles', $this->customAssetFiles(...)),
            new TwigFunction('globalBannerAssetPath', $this->dj->globalBannerAssetPath(...)),
            new TwigFunction('shadowMode', $this->shadowMode(...)),
            new TwigFunction('showDiff', $this->showDiff(...), ['is_safe' => ['html']]),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('printtimediff', $this->printtimediff(...)),
            new TwigFilter('printelapsedminutes', $this->printelapsedminutes(...)),
            new TwigFilter('printtime', $this->printtime(...)),
            new TwigFilter('printHumanTimeDiff', $this->printHumanTimeDiff(...)),
            new TwigFilter('printtimeHover', $this->printtimeHover(...), ['is_safe' => ['html']]),
            new TwigFilter('printResult', $this->printResult(...), ['is_safe' => ['html']]),
            new TwigFilter('printValidJuryResult', $this->printValidJuryResult(...), ['is_safe' => ['html']]),
            new TwigFilter('printValidJurySubmissionResult', $this->printValidJurySubmissionResult(...),
                           ['is_safe' => ['html']]),
            new TwigFilter('printHost', $this->printHost(...), ['is_safe' => ['html']]),
            new TwigFilter('printHosts', $this->printHosts(...), ['is_safe' => ['html']]),
            new TwigFilter('printFiles', $this->printFiles(...), ['is_safe' => ['html']]),
            new TwigFilter('printLazyMode', $this->printLazyMode(...)),
            new TwigFilter('printYesNo', $this->printYesNo(...)),
            new TwigFilter('printSize', Utils::printSize(...), ['is_safe' => ['html']]),
            new TwigFilter('testcaseResults', $this->testcaseResults(...), ['is_safe' => ['html']]),
            new TwigFilter('displayTestcaseResults', $this->displayTestcaseResults(...),
                           ['is_safe' => ['html']]),
            new TwigFilter('externalCcsUrl', $this->externalCcsUrl(...)),
            new TwigFilter('lineCount', $this->lineCount(...)),
            new TwigFilter('base64', 'base64_encode'),
            new TwigFilter('base64_decode', 'base64_decode'),
            new TwigFilter('runDiff', $this->runDiff(...), ['is_safe' => ['html']]),
            new TwigFilter('interactiveLog', $this->interactiveLog(...), ['is_safe' => ['html']]),
            new TwigFilter('codeEditor', $this->codeEditor(...), ['is_safe' => ['html']]),
            new TwigFilter('printContestStart', $this->printContestStart(...)),
            new TwigFilter('assetPath', $this->dj->assetPath(...)),
            new TwigFilter('printTimeRelative', $this->printTimeRelative(...)),
            new TwigFilter('scoreTime', $this->scoreTime(...)),
            new TwigFilter('statusClass', $this->statusClass(...)),
            new TwigFilter('statusIcon', $this->statusIcon(...), ['is_safe' => ['html']]),
            new TwigFilter('countryFlag', $this->countryFlag(...), ['is_safe' => ['html']]),
            new TwigFilter('affiliationLogo', $this->affiliationLogo(...), ['is_safe' => ['html']]),
            new TwigFilter('descriptionExpand', $this->descriptionExpand(...), ['is_safe' => ['html']]),
            new TwigFilter('wrapUnquoted', $this->wrapUnquoted(...)),
            new TwigFilter('hexColorToRGBA', $this->hexColorToRGBA(...)),
            new TwigFilter('tsvField', $this->toTsvField(...)),
            new TwigFilter('fileTypeIcon', $this->fileTypeIcon(...)),
            new TwigFilter('problemBadge', $this->problemBadge(...), ['is_safe' => ['html']]),
            new TwigFilter('problemBadgeForContest', $this->problemBadgeForContest(...), ['is_safe' => ['html']]),
            new TwigFilter('problemBadgeMaybe', $this->problemBadgeMaybe(...), ['is_safe' => ['html']]),
            new TwigFilter('printMetadata', $this->printMetadata(...), ['is_safe' => ['html']]),
            new TwigFilter('printWarningContent', $this->printWarningContent(...), ['is_safe' => ['html']]),
            new TwigFilter('entityIdBadge', $this->entityIdBadge(...), ['is_safe' => ['html']]),
            new TwigFilter('medalType', $this->awards->medalType(...)),
            new TwigFilter('numTableActions', $this->numTableActions(...)),
            new TwigFilter('extensionToMime', $this->extensionToMime(...)),
        ];
    }

    public function getGlobals(): array
    {
        $refresh_cookie = $this->dj->getCookie("domjudge_refresh");
        $refresh_flag   = ($refresh_cookie == null || (bool)$refresh_cookie);

        $user = $this->dj->getUser();
        $team = $user?->getTeam();

        $selfRegistrationCategoriesCount = $this->em->getRepository(TeamCategory::class)->count(['allow_self_registration' => 1]);
        // These variables mostly exist for the header template.
        $currentContest = $this->dj->getCurrentContest();
        return [
            'current_contest_id'            => $this->dj->getCurrentContestCookie(),
            'current_contest'               => $currentContest,
            'current_contests'              => $this->dj->getCurrentContests(),
            'current_public_contest'        => $this->dj->getCurrentContest(onlyPublic: true),
            'current_public_contests'       => $this->dj->getCurrentContests(onlyPublic: true),
            'have_printing'                 => $this->config->get('print_command'),
            'show_languages_to_teams'       => $this->config->get('show_language_versions'),
            'refresh_flag'                  => $refresh_flag,
            'icat_url'                      => $this->config->get('icat_url'),
            'external_ccs_submission_url'   => $this->config->get('external_ccs_submission_url'),
            'current_team_contest'          => $team ? $this->dj->getCurrentContest($team->getTeamid()) : null,
            'current_team_contests'         => $team ? $this->dj->getCurrentContests($team->getTeamid()) : null,
            'submission_languages'          => $this->dj->getAllowedLanguagesForContest($currentContest),
            'alpha3_countries'              => Countries::getAlpha3Names(),
            'alpha3_alpha2_country_mapping' => array_combine(
                Countries::getAlpha3Codes(),
                array_map(fn($alpha3) => Countries::getAlpha2Code($alpha3), Countries::getAlpha3Codes())
            ),
            'show_shadow_differences'       => $this->tokenStorage->getToken() &&
                                               $this->authorizationChecker->isGranted('ROLE_ADMIN') &&
                                               $this->dj->shadowMode(),
            'doc_links'                     => $this->dj->getDocLinks(),
            'allow_registration'            => $selfRegistrationCategoriesCount !== 0,
            'enable_ranking'                => $this->config->get('enable_ranking'),
            'editor_themes'                 => [
                'vs'                        => ['name' => 'Visual Studio (light)'],
                'vs-dark'                   => ['name' => 'Visual Studio (dark)'],
                'Solarized-dark'            => ['name' => 'Solarized (dark)', 'external' => true],
                'Solarized-light'           => ['name' => 'Solarized (light)', 'external' => true],
                'Tomorrow-Night-Blue'       => ['name' => 'Tomorrow Night Blue', 'external' => true],
                'Tomorrow-Night-Bright'     => ['name' => 'Tomorrow Night Bright', 'external' => true],
                'Tomorrow-Night-Eighties'   => ['name' => 'Tomorrow Night Eighties', 'external' => true],
                'Tomorrow-Night'            => ['name' => 'Tomorrow Night', 'external' => true],
                'Tomorrow'                  => ['name' => 'Tomorrow', 'external' => true],
                'hc-light'                  => ['name' => 'High contrast (light)'],
                'hc-black'                  => ['name' => 'High contrast (dark)'],
            ],
            'diff_modes'                    => [
                'no-diff'                   => ["name"  => "No diff"],
                'side-by-side'              => ["name"  => "Side-by-side"],
                'inline'                    => ["name"  => "Inline"],
            ],
        ];
    }

    public function printtimediff(float $start, ?float $end = null): string
    {
        return Utils::printtimediff($start, $end);
    }

    public function printelapsedminutes(float $start, float $end): string
    {
        $minutesElapsed = floor(($end - $start)/60);
        if ($minutesElapsed < 1) {
            return 'started less than 1 minute ago';
        } elseif ($minutesElapsed == 1) {
            return 'started 1 minute ago';
        } else {
            return 'started ' . $minutesElapsed . ' minutes ago';
        }
    }

    /**
     * Print a time formatted as specified. The format is according to date().
     * @param Contest|null $contest If given, print time relative to that contest start.
     */
    public function printtime(string|float|null $datetime, ?string $format = null, ?Contest $contest = null): string
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

    public function printHumanTimeDiff(float|null $startTime = null, float|null $endTime = null): string
    {
        if ($startTime === null) {
            return '';
        }
        $suffix = '';
        if ($endTime === null) {
            $suffix = ' ago';
            $endTime = Utils::now();
        }
        $diff = $endTime - $startTime;

        if ($diff < 120) {
            return (int)($diff) . ' seconds' . $suffix;
        }
        $diff /= 60;
        if ($diff < 120) {
            return (int)($diff) . ' minutes' . $suffix;
        }
        $diff /= 60;
        if ($diff < 48) {
            return (int)($diff) . ' hours' . $suffix;
        }
        $diff /= 24;
        return (int)($diff) . ' days' . $suffix;
    }

    /**
     * Helper function to print a time in the default/configured format,
     * and a hover title attribute with the full datetime string.
     *
     * @param Contest|null $contest If given, print time relative to that contest start.
     */
    public function printtimeHover(string|float $datetime, ?Contest $contest = null): string
    {
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
        return match ($status) {
            'noconn' => 'text-muted',
            'crit' => 'text-danger',
            'warn' => 'text-warning',
            'ok' => 'text-success',
            default => '',
        };
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
                return $status;
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
        } catch (MissingResourceException) {
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
                           htmlspecialchars($assetUrl), htmlspecialchars($shortName));
        }

        return '';
    }

    public function testcaseResults(Submission $submission, ?bool $showExternal = false): string
    {
        // We use a direct SQL query here for performance reasons
        if ($showExternal) {
            /** @var ExternalJudgement|null $externalJudgement */
            $externalJudgement   = $submission->getExternalJudgements()->first() ?: null;
            $externalJudgementId = $externalJudgement?->getExtjudgementid();
            $probId              = $submission->getProblem()->getProbid();
            $testcases           = $this->em->getConnection()->fetchAllAssociative(
                'SELECT er.result as runresult, t.ranknumber, t.description, t.sample
                  FROM testcase t
                  LEFT JOIN external_run er ON (er.testcaseid = t.testcaseid
                                              AND er.extjudgementid = :extjudgementid)
                  WHERE t.probid = :probid ORDER BY ranknumber',
                ['extjudgementid' => $externalJudgementId, 'probid' => $probId]);

            $submissionDone = $externalJudgement && !empty($externalJudgement->getEndtime());
        } else {
            /** @var Judging|bool $judging */
            $judging   = $submission->getJudgings()->first();
            $judgingId = $judging ? $judging->getJudgingid() : null;
            $probId    = $submission->getProblem()->getProbid();
            $testcases = $this->em->getConnection()->fetchAllAssociative(
                'SELECT r.runresult, jh.hostname, jt.valid, t.ranknumber, t.description, t.sample
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
        $lastTypeSample = true;
        foreach ($testcases as $key => $testcase) {
            if ($testcase['sample'] != $lastTypeSample) {
                $results        .= ' | ';
                $lastTypeSample = $testcase['sample'];
            }
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
                                 htmlspecialchars($testcase['description']));
            } else {
                $title = sprintf('Run %d', $key + 1);
            }

            $results .= sprintf('<span class="badge text-bg-%s badge-testcase" title="%s">%s</span>', $class, $title,
                                $text);
        }

        return $results;
    }

    // TODO: this function shares a lot with the above one, unify them?
    /**
     * @param Testcase[] $testcases
     */
    public function displayTestcaseResults(array $testcases, bool $submissionDone, bool $isExternal = false): string
    {
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
            /** @var JudgingRun|ExternalRun|null $run */
            $run = $isExternal ? $testcase->getFirstExternalRun() : $testcase->getFirstJudgingRun();
            if ($isExternal) {
                $runResult = $run?->getResult();
            } else {
                $runResult = $run?->getRunresult();
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
            $icon    = sprintf('<span class="badge text-bg-%s badge-testcase">%s</span>', $class, $text);
            $results .= sprintf('<a title="%s" href="#run-%d" %s>%s</a>',
                                join(', ', $titleElements), $testcase->getRank(),
                                $isCorrect ? 'onclick="display_correctruns(true);"' : '', $icon);
        }

        return $results;
    }

    public function printResult(
        ?string $result,
        bool $valid = true,
        bool $jury = false,
        bool $onlyRejectedForIncorrect = false,
    ): string {
        $result = strtolower($result ?? '');
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
                if ($onlyRejectedForIncorrect) {
                    $result = 'rejected';
                }
        }

        return sprintf('<span class="sol %s">%s</span>', $valid ? $style : 'disabled', $result);
    }

    public function printValidJuryResult(?string $result): string
    {
        return $this->printResult($result, true, true);
    }

    public function printValidJurySubmissionResult(Submission $submission, bool $forDisplay = true): string
    {
        if ($submission->isImportError()) {
            $result = 'import-error';
            return $forDisplay ? $this->printValidJuryResult($result) : $result;
        }

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
            return str_replace(['[contest]', '[id]'],
                [$submission->getContest()->getExternalid(), $submission->getExternalid()],
                $extCcsUrl);
        }

        return null;
    }

    /**
     * Prints the first file (and potentially the number of additional files).
     *
     * @param Collection<int, SubmissionFile> $files
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
            return sprintf('<span class="hostname">%s</span>', htmlspecialchars($firstFile));
        }
        return sprintf('<span class="filename">%s</span> (and %d more)',
                       htmlspecialchars($firstFile),
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

        return sprintf('<span class="hostname">%s</span>', htmlspecialchars($hostname));
    }

    /**
     * Extract the longest common prefix of all the provided strings.
     *
     * @param string[] $strings
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
     *
     * @param string[] $hostnames
     */
    public function printHosts(array $hostnames): string
    {
        $hostnames = array_values($hostnames);
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
            return implode(", ", array_map($this->printHost(...), $hostnames));
        } else {
            $hosts = $common_prefix . "{" . implode(",", $middle_parts) . "}" . $common_suffix;
            return $this->printHost($hosts, true);
        }
    }

    public function lineCount(string $input): int
    {
        return mb_substr_count($input, "\n");
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
            $is_validator = $log[$idx] == '>' || $log[$idx] == ']';
            if ($log[$idx] == ']' || $log[$idx] == '[') {
                $content = '<td style="font-style:italic; color: dimgrey;">EOF from program</td>';
            } else {
                $content = substr($log, $idx + 3, $len);
                if (empty($content)) {
                    break;
                }
                $content = htmlspecialchars($content);
                $content = '<td class="output_text">'
                    . str_replace("\n", "\u{21B5}<br/>", $content)
                    . '</td>';
            }
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

    /**
     * @param array{output_run: string, output_reference: string} $runOutput
     */
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
     * @param string|null $language Editor language to use
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
$(function() {
    require(['vs/editor/editor.main'], function () {
        const element = document.getElementById('__EDITOR__');
        const content = element.textContent;
        element.textContent = '';

        const editor = monaco.editor.create(element, {
            value: content,
            scrollbar: {
                vertical: 'auto',
                horizontal: 'auto'
            },
            scrollBeyondLastLine: false,
            automaticLayout: true,
            readOnly: %s,
            theme: getCurrentEditorTheme(),
        });
        %s
        %s
    });
});
</script>
HTML;
        $rank   = $index;
        $id     = sprintf('editor%s', $rank);
        $code   = htmlspecialchars($code);
        if ($elementToUpdate) {
            $extraForEdit = <<<JS
editor.getModel().onDidChangeContent(() => {
    const newValue = editor.getValue();
    const textarea = document.getElementById("$elementToUpdate");
    textarea.value = newValue;
});
JS;
        } else {
            $extraForEdit = '';
        }

        if ($language !== null) {
            $mode = <<<JS
const model = editor.getModel();
model.setLanguage("$language");
JS;
        } elseif ($filename !== null) {
            $modeTemplate = <<<JS
const filePath = "%s";
const model = monaco.editor.createModel(content, undefined, monaco.Uri.file(filePath));
editor.setModel(model);
JS;
            $mode         = sprintf($modeTemplate, htmlspecialchars($filename));
        } else {
            $mode = '';
        }

        return str_replace('__EDITOR__', $id,
                           sprintf($editor, $code, $editable ? 'false' : 'true', $mode, $extraForEdit));
    }

    public function showDiff(string $id, SubmissionFile $newFile, SubmissionFile $oldFile): string
    {
        $editor = <<<HTML
<div class="editor" id="__EDITOR__"></div>
<script>
$(function() {
    require(['vs/editor/editor.main'], function () {
        const originalModel = monaco.editor.createModel(
            "%s",
            undefined,
            monaco.Uri.parse("diff-old/%s")
        );
        const modifiedModel = monaco.editor.createModel(
            "%s",
            undefined,
            monaco.Uri.parse("diff-new/%s")
        );

        const initialDiffMode = getDiffMode();
        const radios = $("#diffselect-__EDITOR__ > input[name='__EDITOR__-mode']");
        radios.each((_, radio) => {
            $(radio).prop('checked', radio.value === initialDiffMode);
        });

        const diffEditor = monaco.editor.createDiffEditor(
            document.getElementById("__EDITOR__"), {
            scrollbar: {
                vertical: 'auto',
                horizontal: 'auto'
            },
            scrollBeyondLastLine: false,
            automaticLayout: true,
            readOnly: true,
            theme: getCurrentEditorTheme(),
        });

        const updateMode = (diffMode) => {
            setDiffMode(diffMode);
            const noDiff = diffMode === 'no-diff';
            diffEditor.updateOptions({
                renderOverviewRuler: !noDiff,
                renderSideBySide: diffMode === 'side-by-side',
            });

            const oldViewState = diffEditor.saveViewState();
            diffEditor.setModel({
                original: noDiff ? modifiedModel : originalModel,
                modified: modifiedModel,
            });
            diffEditor.restoreViewState(oldViewState);

            diffEditor.getOriginalEditor().updateOptions({
                lineNumbers: !noDiff,
            });
            diffEditor.getModifiedEditor().updateOptions({
                minimap: {
                    enabled: noDiff,
                },
            })
        };
        radios.change((e) => {
            updateMode(e.target.value);
        });
        updateMode(initialDiffMode);
    });
});
</script>
HTML;

        return sprintf(
            str_replace('__EDITOR__', $id, $editor),
            $this->twig->getRuntime(EscaperRuntime::class)->escape($oldFile->getSourcecode(), 'js'),
            $oldFile->getFilename(),
            $this->twig->getRuntime(EscaperRuntime::class)->escape($newFile->getSourcecode(), 'js'),
            $newFile->getFilename(),
        );
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

    /**
     * @return string[]
     */
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
     */
    public function scoreTime(string|float $time): int
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
            $defaultEscaped  = htmlspecialchars($default);
            $expandedEscaped = htmlspecialchars(implode('<br>', $descriptionLines));
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

    public function shadowMode(): bool
    {
        return $this->dj->shadowMode();
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
        $ret = preg_match_all("/[0-9A-Fa-f]{2}/", $col, $m);
        if (!($ret && count($m[0]))) {
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
        $iconName = match ($type) {
            'pdf' => 'pdf',
            'txt' => 'alt',
            default => 'code',
        };

        return 'fas fa-file-' . $iconName;
    }

    private function relativeLuminance(string $rgb): float
    {
        // See https://en.wikipedia.org/wiki/Relative_luminance
        [$r, $g, $b] = Utils::parseHexColor($rgb);

        [$lr, $lg, $lb] = [
            pow($r / 255, 2.4),
            pow($g / 255, 2.4),
            pow($b / 255, 2.4),
        ];

        return 0.2126 * $lr + 0.7152 * $lg + 0.0722 * $lb;
    }

    private function apcaContrast(string $fgColor, string $bgColor): float
    {
        // Based on WCAG 3.x (https://www.w3.org/TR/wcag-3.0/)
        $luminanceForeground = $this->relativeLuminance($fgColor);
        $luminanceBackground = $this->relativeLuminance($bgColor);

        $contrast = ($luminanceBackground > $luminanceForeground)
            ? (pow($luminanceBackground, 0.56) - pow($luminanceForeground, 0.57)) * 1.14
            : (pow($luminanceBackground, 0.65) - pow($luminanceForeground, 0.62)) * 1.14;

        return round($contrast * 100, 2);
    }

    /**
     * @return array{string, string}
     */
    private function hexToForegroundAndBorder(string $rgb): array
    {
        $background = Utils::parseHexColor($rgb);

        // Pick a border that's a bit darker.
        $darker = $background;
        $darker[0] = max($darker[0] - 64, 0);
        $darker[1] = max($darker[1] - 64, 0);
        $darker[2] = max($darker[2] - 64, 0);
        $border    = Utils::rgbToHex($darker);

        // Pick the text color with the biggest absolute contrast.
        $contrastWithWhite = $this->apcaContrast('#ffffff', $rgb);
        $contrastWithBlack = $this->apcaContrast('#000000', $rgb);

        $foreground = (abs($contrastWithBlack) > abs($contrastWithWhite)) ? '#000000' : '#ffffff';

        return [$foreground, $border];
    }

    public function problemBadge(ContestProblem $problem, bool $grayedOut = false): string
    {
        $rgb = Utils::convertToHex($problem->getColor() ?? '#ffffff');
        if ($grayedOut || empty($rgb)) {
            $rgb = Utils::convertToHex('whitesmoke');
        }

        [$foreground, $border] = $this->hexToForegroundAndBorder($rgb);

        if ($grayedOut) {
            $foreground = 'silver';
            $border = 'linen';
        }
        return sprintf(
            '<span class="badge problem-badge" style="background-color: %s; border: 1px solid %s"><span style="color: %s;">%s</span></span>',
            $rgb,
            $border,
            $foreground,
            $problem->getShortname()
        );
    }

    public function problemBadgeMaybe(
        ContestProblem $problem,
        ScoreboardMatrixItem $matrixItem,
        TeamScore $score,
        bool $static = false,
    ): string {
        $rgb = Utils::convertToHex($problem->getColor() ?? '#ffffff');
        if (!$matrixItem->isCorrect || empty($rgb)) {
            $rgb = Utils::convertToHex('whitesmoke');
        }

        [$foreground, $border] = $this->hexToForegroundAndBorder($rgb);

        if (!$matrixItem->isCorrect) {
            $foreground = 'silver';
            $border = 'linen';
        }

        $submissionsUrl = $static
            ? $this->router->generate('public_submissions_data')
            : $this->router->generate('public_submissions_data_cell', [
                'teamId' => $score->team->getExternalid(),
                'problemId' => $problem->getExternalId(),
            ]);

        $ret = sprintf(<<<HTML
                <span class="badge problem-badge"
                      style="font-size: x-small; background-color: %s; min-width: 18px; border: 1px solid %s;"
                      data-submissions-url="%s"
                      data-team-id="%s"
                      data-problem-id="%s">
                    <span style="color: %s;">%s</span>
                </span>
                HTML,
            $rgb,
            $border,
            $submissionsUrl,
            $score->team->getExternalid(),
            $problem->getExternalId(),
            $foreground,
            $problem->getShortname()
        );
        if (!$matrixItem->isCorrect) {
            if ($matrixItem->numSubmissionsPending > 0) {
                $ret = '<span><span class="mobile-pending">' . $ret . '</span></span>';
            } elseif ($matrixItem->numSubmissions > 0) {
                $ret = '<span><span class="strike-diagonal">' . $ret . '</span></span>';
            }
        }
        return $ret;
    }

    public function problemBadgeForContest(Problem $problem, ?Contest $contest = null): string
    {
        $contest ??= $this->dj->getCurrentContest();
        $contestProblem = $contest?->getContestProblem($problem);
        return $contestProblem === null ? '' : $this->problemBadge($contestProblem);
    }

    public function printMetadata(?string $metadata): string
    {
        if ($metadata === null) {
            return '';
        }
        $metadata = Utils::parseMetadata($metadata);
        $result = '<span style="display:inline; margin-left: 5px;">';

        if (isset($metadata['cpu-time']) || isset($metadata['wall-time'])) {
            $result .= '<i class="fas fa-stopwatch" title="runtime"></i> ';
        }

        if (isset($metadata['cpu-time'])) {
            $result .= $metadata['cpu-time'] . 's CPU, ';
        }
        if (isset($metadata['wall-time'])) {
            $result .= $metadata['wall-time'] . 's wall, ';
        }
        if (isset($metadata['memory-bytes'])) {
            $result .= '<i class="fas fa-memory" title="RAM"></i> '
                . Utils::printsize((int)($metadata['memory-bytes'])) . ', ';
        }
        if (isset($metadata['exitcode'])) {
            $result .= '<i class="far fa-question-circle" title="exit-status"></i> '
                . 'exit-code: ' . $metadata['exitcode'];
        }
        if (isset($metadata['signal'])) {
            $result .= ' signal: ' . $metadata['signal'];
        }
        $result .= '</span>';
        return $result;
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
                        $diff['us'] ?? $null
                    );
                    $tdExternal = sprintf(
                        '<td><code>%s</code></td>',
                        $diff['external'] ?? $null
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
        $metadata = $this->em->getClassMetadata($entity::class);
        $primaryKeyColumn = $metadata->getIdentifierColumnNames()[0];

        $data = [
            'idPrefix' => $idPrefix,
            'id' => $propertyAccessor->getValue($entity, $primaryKeyColumn),
            'externalId' => null,
        ];

        if ($entity instanceof HasExternalIdInterface) {
            $data['externalId'] = $entity->getExternalid();
        }

        if ($entity instanceof Team) {
            $data['label'] = $entity->getLabel();
        }

        return $this->twig->render('jury/entity_id_badge.html.twig', $data);
    }

    /**
     * @param array<array{data: array<string, array{value: string, sortvalue?: string, title?: string, cssclass?: string}>,
     *                    link: string,
     *                    actions?: array{icon: string, title: string, link: string, ajaxModal?: bool},
     *                    cssclass?: string
     *        }> $tableData
     */
    protected function numTableActions(array $tableData): int
    {
        $maxNumActions = 0;
        foreach ($tableData as $item) {
            $maxNumActions = max($maxNumActions, count($item['actions'] ?? []));
        }
        return $maxNumActions;
    }

    public function extensionToMime(string $extension): string
    {
        return DOMJudgeService::EXTENSION_TO_MIMETYPE[$extension];
    }
}
