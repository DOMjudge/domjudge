<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\Contest;
use App\Entity\ExternalJudgement;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Submission;
use App\Entity\SubmissionFile;
use App\Entity\Testcase;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use SebastianBergmann\Diff\Differ;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var SubmissionService
     */
    protected $submissionService;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var AuthorizationCheckerInterface
     */
    protected $authorizationChecker;

    /**
     * @var string
     */
    protected $projectDir;

    public function __construct(
        DOMJudgeService $dj,
        ConfigurationService $config,
        EntityManagerInterface $em,
        SubmissionService $submissionService,
        EventLogService $eventLogService,
        TokenStorageInterface $tokenStorage,
        AuthorizationCheckerInterface $authorizationChecker,
        string $projectDir
    ) {
        $this->dj                   = $dj;
        $this->config               = $config;
        $this->em                   = $em;
        $this->submissionService    = $submissionService;
        $this->eventLogService      = $eventLogService;
        $this->tokenStorage         = $tokenStorage;
        $this->authorizationChecker = $authorizationChecker;
        $this->projectDir           = $projectDir;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('button', [$this, 'button'], ['is_safe' => ['html']]),
            new TwigFunction('calculatePenaltyTime', [$this, 'calculatePenaltyTime']),
            new TwigFunction('showExternalId', [$this, 'showExternalId']),
        ];
    }

    public function getFilters()
    {
        return [
            new TwigFilter('printtimediff', [$this, 'printtimediff']),
            new TwigFilter('printtime', [$this, 'printtime']),
            new TwigFilter('printtimeHover', [$this, 'printtimeHover'], ['is_safe' => ['html']]),
            new TwigFilter('printResult', [$this, 'printResult'], ['is_safe' => ['html']]),
            new TwigFilter('printValidJuryResult', [$this, 'printValidJuryResult'], ['is_safe' => ['html']]),
            new TwigFilter('printHost', [$this, 'printHost'], ['is_safe' => ['html']]),
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
            new TwigFilter('assetExists', [$this, 'assetExists']),
            new TwigFilter('printTimeRelative', [$this, 'printTimeRelative']),
            new TwigFilter('scoreTime', [$this, 'scoreTime']),
            new TwigFilter('statusClass', [$this, 'statusClass']),
            new TwigFilter('statusIcon', [$this, 'statusIcon']),
            new TwigFilter('descriptionExpand', [$this, 'descriptionExpand'], ['is_safe' => ['html']]),
            new TwigFilter('wrapUnquoted', [$this, 'wrapUnquoted']),
            new TwigFilter('hexColorToRGBA', [$this, 'hexColorToRGBA']),
            new TwigFilter('tsvField', [$this, 'toTsvField']),
        ];
    }

    public function getGlobals()
    {
        $refresh_cookie = $this->dj->getCookie("domjudge_refresh");
        $refresh_flag   = ($refresh_cookie == null || (bool)$refresh_cookie);

        require_once $this->dj->getDomjudgeEtcDir() . '/domserver-config.php';

        $user = $this->dj->getUser();
        $team = $user ? $user->getTeam() : null;

        // These variables mostly exist for the header template
        return [
            'current_contest' => $this->dj->getCurrentContest(),
            'current_contests' => $this->dj->getCurrentContests(),
            'current_public_contest' => $this->dj->getCurrentContest(-1),
            'current_public_contests' => $this->dj->getCurrentContests(-1),
            'have_printing' => $this->config->get('print_command'),
            'refresh_flag' => $refresh_flag,
            'icat_url' => defined('ICAT_URL') ? ICAT_URL : null,
            'external_ccs_submission_url' => $this->config->get('external_ccs_submission_url'),
            'current_team_contest' => $team ? $this->dj->getCurrentContest($user->getTeamid()) : null,
            'current_team_contests' => $team ? $this->dj->getCurrentContests($user->getTeamid()) : null,
            'submission_languages' => $this->em->createQueryBuilder()
                ->from(Language::class, 'l')
                ->select('l')
                ->andWhere('l.allowSubmit = 1')
                ->getQuery()
                ->getResult(),
            'alpha3_countries' => Utils::ALPHA3_COUNTRIES,
            'show_shadow_differences' => $this->tokenStorage->getToken() &&
                                         $this->authorizationChecker->isGranted('ROLE_ADMIN') &&
                                         $this->config->get('data_source') === DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL,
        ];
    }

    /**
     * Print the time difference between two times
     * @param float      $start
     * @param float|null $end
     * @return string
     */
    public function printtimediff(float $start, float $end = null): string
    {
        return Utils::printtimediff($start, $end);
    }

    /**
     * Print a time formatted as specified. The format is according to strftime().
     * @param string|float $datetime
     * @param string|null  $format
     * @param Contest|null $contest If given, print time relative to that contest start.
     * @return string
     * @throws \Exception
     */
    public function printtime($datetime, string $format = null, Contest $contest = null): string
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
     * @return string
     * @throws \Exception
     */
    public function printtimeHover($datetime, Contest $contest = null): string
    {
        if ($datetime === null) {
            $datetime = Utils::now();
        }
        return '<span title="' .
            Utils::printtime($datetime, '%Y-%m-%d %H:%M (%Z)') . '">' .
            $this->printtime($datetime, null, $contest) .
            '</span>';
    }

    /**
     * print a yes/no field
     * @param bool $val
     * @return string
     */
    public static function printYesNo(bool $val): string
    {
        return $val ? 'Yes' : 'No';
    }

    /**
     * render a button
     * @param string      $url
     * @param string      $text
     * @param string      $type
     * @param string|null $icon
     * @param bool        $isAjaxModal
     * @return string
     */
    public function button(
        string $url,
        string $text,
        string $type = 'primary',
        string $icon = null,
        bool $isAjaxModal = false
    ) {
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

    /**
     * Map user/team/judgehost status to a cssclass
     * @param string $status
     * @return string
     */
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

    /**
     * Map user/team/judgehost status to an icon
     * @param string $status
     * @return string
     */
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
        return sprintf('<i class="fas fa-%s-circle"></i>', $icon);
    }

    /**
     * Output the testcase results for the given submissions
     * @param Submission $submission
     * @param bool       $external If true, show external testcase results
     * @return string
     */
    public function testcaseResults(Submission $submission, bool $external = false)
    {
        // We use a direct SQL query here for performance reasons
        if ($external) {
            /** @var ExternalJudgement|null $externalJudgement */
            $externalJudgement   = $submission->getExternalJudgements()->first();
            $externalJudgementId = $externalJudgement ? $externalJudgement->getExtjudgementid() : null;
            $probId              = $submission->getProbid();
            $testcases           = $this->em->getConnection()->fetchAll(
                'SELECT er.result as runresult, t.rank, t.description
                  FROM testcase t
                  LEFT JOIN external_run er ON (er.testcaseid = t.testcaseid
                                              AND er.extjudgementid = :extjudgementid)
                  WHERE t.probid = :probid ORDER BY rank',
                [':extjudgementid' => $externalJudgementId, ':probid' => $probId]);

            $submissionDone = $externalJudgement ? !empty($externalJudgement->getEndtime()) : false;
        } else {
            /** @var Judging|null $judging */
            $judging   = $submission->getJudgings()->first();
            $judgingId = $judging ? $judging->getJudgingid() : null;
            $probId    = $submission->getProbid();
            $testcases = $this->em->getConnection()->fetchAll(
                'SELECT r.runresult, t.rank, t.description
                  FROM testcase t
                  LEFT JOIN judging_run r ON (r.testcaseid = t.testcaseid
                                              AND r.judgingid = :judgingid)
                  WHERE t.probid = :probid ORDER BY rank',
                [':judgingid' => $judgingId, ':probid' => $probId]);

            $submissionDone = $judging ? !empty($judging->getEndtime()) : false;
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

    /**
     * Display testcase results
     *
     * TODO: this function shares a lot with the above one, unify them?
     *
     * @param Testcase[] $testcases
     * @param bool       $submissionDone
     * @param bool       $isExternal
     * @return string
     */
    public function displayTestcaseResults(array $testcases, bool $submissionDone, bool $isExternal = false)
    {
        $results = '';
        $lastTypeSample = true;
        foreach ($testcases as $testcase) {
            if ($testcase->getSample() != $lastTypeSample) {
                $results .= ' | ';
                $lastTypeSample = $testcase->getSample();
            }

            $class     = $submissionDone ? 'secondary' : 'primary';
            $text      = '?';
            $isCorrect = false;
            $run       = $isExternal ? $testcase->getFirstExternalRun() : $testcase->getFirstJudgingRun();
            if ($isExternal) {
                $runResult = $run ? $run->getResult() : null;
            } else {
                $runResult = $run ? $run->getRunresult() : null;
            }

            if ($run && $runResult !== null) {
                $text  = substr($runResult, 0, 1);
                $class = 'danger';
                if ($runResult === Judging::RESULT_CORRECT) {
                    $isCorrect = true;
                    $text      = '✓';
                    $class     = 'success';
                }
            }

            $titleElements = array("#" . $testcase->getRank());
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
            $icon     = sprintf('<span class="badge badge-%s badge-testcase">%s</span>', $class, $text);
            $results .= sprintf('<a title="%s" href="#run-%d" %s>%s</a>',
                                join(', ', $titleElements), $testcase->getRank(),
                                $isCorrect ? 'onclick="display_correctruns(true);"' : '', $icon);
        }

        return $results;
    }

    /**
     * Print the given result
     * @param string $result
     * @param bool   $valid
     * @param bool   $jury
     * @return string
     */
    public function printResult($result, bool $valid = true, bool $jury = false): string
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

    /**
     * Print the given result for the jury, assuming it is valid
     * @param string $result
     * @return string
     */
    public function printValidJuryResult($result): string
    {
        return $this->printResult($result, true, true);
    }

    /**
     * Return the URL to an external CCS for the given submission if available
     * @param Submission $submission
     * @return string|null
     * @throws \Exception
     */
    public function externalCcsUrl(Submission $submission)
    {
        require_once $this->dj->getDomjudgeEtcDir() . '/domserver-config.php';

        $extCcsUrl = $this->config->get('external_ccs_submission_url');
        if (!empty($extCcsUrl)) {
            $dataSource = $this->config->get('data_source');
            if ($dataSource == 2) {
                return str_replace(['[contest]', '[id]'], [$submission->getContest()->getExternalid(), $submission->getExternalid()], $extCcsUrl);
            } elseif ($dataSource == 1) {
                return str_replace(['[contest]', '[id]'], [$submission->getContest()->getExternalid(), $submission->getSubmitid()], $extCcsUrl);
            }
        }

        return null;
    }

    /**
     * Formats a given hostname. If $full = true, then the full hostname will be printed,
     * else only the local part (for keeping tables readable)
     * @param string $hostname
     * @param bool   $full
     * @return string
     */
    public function printHost(string $hostname, bool $full = false): string
    {
        // Shorten the hostname to first label, but not if it's an IP address.
        if (!$full && !preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $hostname)) {
            $expl     = explode('.', $hostname);
            $hostname = array_shift($expl);
        }

        return sprintf('<span class="hostname">%s</span>', Utils::specialchars($hostname));
    }

    /**
     * Get the number of lines in a given string
     * @param string $input
     * @return int
     */
    public function lineCount(string $input): int
    {
        return mb_substr_count($input, "\n");
    }

    /**
     * Parse the run diff for a given difftext
     * @param string $difftext
     * @return string
     */
    public function parseRunDiff(string $difftext): string
    {
        $line = strtok($difftext, "\n"); //first line
        if ($line === false || sscanf($line, "### DIFFERENCES FROM LINE %d ###\n", $firstdiff) != 1) {
            return Utils::specialchars($difftext);
        }
        $return = $line . "\n";

        // Add second line 'team ? reference'
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

    public function interactiveLog(string $log) {
        $truncated = '/\[output display truncated after \d* B\]$/';
        $matches = array();
        $truncation = "";
        if (preg_match($truncated, $log, $matches)) {
            $truncation = $matches[0];
            $log = preg_replace($truncated, "", $log);
        }
        $header = "<table><tr><th>time</th><th>validator</th><th>submission<th></tr>\n";
        $body = "";
        $idx = 0;
        while ($idx < strlen($log)) {
            $slashPos = strpos($log, "/", $idx);
            if ($slashPos === FALSE) break;
            $time = substr($log, $idx + 1, $slashPos - $idx - 1);
            $idx = $slashPos + 1;
            $closePos = strpos($log, "]", $idx);
            if ($closePos === FALSE) {
                break;
            }
            $lenStr = substr($log, $idx, $closePos - $idx);
            $len = (int)$lenStr;
            if ($idx + 3 + $len >= strlen($log)) {
                break;
            }
            $idx = $closePos + 1;
            $is_validator = $log[$idx] == '>';
            $content = htmlspecialchars(substr($log, $idx + 3, $len));
            $content = '<td class="output_text">'
                . str_replace("\n", "\u{21B5}<br/>", $content)
                . '</td>';
            $idx += $len + 4;
            $team = $is_validator ? '<td/>' : $content;
            $validator = $is_validator ? $content : '<td/>';
            $body .= "<tr><td>$time</td>"
                . $validator
                . $team
                . "</tr>\n";
        }
        return $header . $body . "</table>" . $truncation;
    }

    /**
     * Output a run diff
     * @param array $runOutput
     * @return string
     * @throws \Exception
     */
    public function runDiff(array $runOutput)
    {
        // TODO: can be improved using diffposition.txt
        // FIXME: only show when diffposition.txt is set?
        // FIXME: cut off after XXX lines
        $lines_team = preg_split('/\n/', trim($runOutput['output_run']));
        $lines_ref  = preg_split('/\n/', trim($runOutput['output_reference']));

        $diffs    = array();
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
     * Output a (readonly) code editor for the given submission file
     * @param string      $code
     * @param string      $index
     * @param string|null $language        Ace language to use
     * @param bool        $editable        Whether to allow editing
     * @param string      $elementToUpdate HTML element to update when input changes
     * @param string|null $filename        If $language is null, filename to use to determine language
     * @return string
     */
    public function codeEditor(
        string $code,
        string $index,
        string $language = null,
        bool $editable = false,
        string $elementToUpdate = '',
        string $filename = null
    ) {
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


    /**
     * Parse the given source diff
     * @param $difftext
     * @return string
     */
    protected function parseSourceDiff($difftext)
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

    /**
     * Show a diff between two files
     * @param SubmissionFile $newFile
     * @param SubmissionFile $oldFile
     * @return string
     */
    public function showDiff(SubmissionFile $newFile, SubmissionFile $oldFile)
    {
        $differ = new Differ;
        return $this->parseSourceDiff($differ->diff($newFile->getSourcecode(), $oldFile->getSourcecode()));
    }

    /**
     * Print the start time of the given contest
     * @param Contest $contest
     * @return string
     * @throws \Exception
     */
    public function printContestStart(Contest $contest): string
    {
        $res = "scheduled to start ";
        if (!$contest->getStarttimeEnabled()) {
            $res = "start delayed, was scheduled ";
        }
        if ($this->printtime(Utils::now(), '%Y%m%d') == $this->printtime($contest->getStarttime(), '%Y%m%d')) {
            // Today
            $res .= "at " . $this->printtime($contest->getStarttime());
        } else {
            // Print full date
            $res .= "on " . $this->printtime($contest->getStarttime(), '%a %d %b %Y %T %Z');
        }
        return $res;
    }

    /**
     * Determine whether the given asset exists
     * @param string $asset
     * @return bool
     */
    public function assetExists(string $asset): bool
    {
        $webDir = realpath(sprintf('%s/public', $this->projectDir));
        return is_readable($webDir . '/' . $asset);
    }

    /**
     * Print the relative time in h:mm:ss[.uuuuuu] format.
     * @param float $relativeTime
     * @param bool  $useMicroseconds
     * @return string
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
     * Display the scoretime for the given time
     * @param string|float $time
     * @return int
     * @throws \Exception
     */
    public function scoreTime($time)
    {
        return Utils::scoretime($time, (bool)$this->config->get('score_in_seconds'));
    }

    /**
     * Calculate the penalty time for the given data
     * @param bool $solved
     * @param int  $num_submissions
     * @return int
     * @throws \Exception
     */
    public function calculatePenaltyTime(bool $solved, int $num_submissions)
    {
        return Utils::calcPenaltyTime($solved, $num_submissions, (int)$this->config->get('penalty_time'),
                                      (bool)$this->config->get('score_in_seconds'));
    }

    /**
     * Print the given description, collapsing it by default if it is too big
     * @param string|null $description
     * @return string
     */
    public function descriptionExpand(string $description = null): string
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
     * Whether to show the external ID for the given entity
     * @param object|string $entity
     * @return bool
     * @throws \Exception
     */
    public function showExternalId($entity): Bool
    {
        return $this->eventLogService->externalIdFieldForEntity($entity) !== null;
    }

    /**
     * Wrap unquoted text
     * @param string $text
     * @param int    $width
     * @param string $quote
     * @return string
     */
    public function wrapUnquoted(string $text, int $width = 75, string $quote = '>'): string
    {
        return Utils::wrapUnquoted($text, $width, $quote);
    }

    /**
     * Convert a hex color to RGBA
     * @param string $text
     * @param float  $opacity
     * @return string
     */
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
                // We also have opacity; load that
                $opacity = hexdec(array_pop($m));
            case 3:
                $vals = array_map("hexdec", $m);
                $vals[] = $opacity;

                return "rgba(" . implode(",", $vals) . ")";
        }

        return $text;
    }

    /**
     * Convert the given string to a field that is safe to use in a TSV file
     *
     * @param string $field
     *
     * @return string
     */
    public function toTsvField(string $field)
    {
        return Utils::toTsvField($field);
    }
}
