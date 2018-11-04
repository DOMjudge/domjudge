<?php declare(strict_types=1);

namespace DOMJudgeBundle\Twig;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Judging;
use DOMJudgeBundle\Entity\JudgingRunWithOutput;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Entity\Testcase;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Utils\Utils;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\TwigFunction;

class TwigExtension extends \Twig_Extension implements \Twig_Extension_GlobalsInterface
{
    /**
     * @var DOMJudgeService
     */
    protected $domjudge;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var KernelInterface
     */
    protected $kernel;

    public function __construct(
        DOMJudgeService $domjudge,
        EntityManagerInterface $entityManager,
        KernelInterface $kernel
    ) {
        $this->domjudge      = $domjudge;
        $this->entityManager = $entityManager;
        $this->kernel        = $kernel;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('button', [$this, 'button'], ['is_safe' => ['html']]),
        ];
    }

    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('timediff', [$this, 'timediff']),
            new \Twig_SimpleFilter('printtime', [$this, 'printtime']),
            new \Twig_SimpleFilter('printResult', [$this, 'printResult'], ['is_safe' => ['html']]),
            new \Twig_SimpleFilter('printHost', [$this, 'printHost'], ['is_safe' => ['html']]),
            new \Twig_SimpleFilter('testcaseReults', [$this, 'testcaseReults'], ['is_safe' => ['html']]),
            new \Twig_SimpleFilter('displayTestcaseResults', [$this, 'displayTestcaseResults'],
                                   ['is_safe' => ['html']]),
            new \Twig_SimpleFilter('externalCcsUrl', [$this, 'externalCcsUrl']),
            new \Twig_SimpleFilter('lineCount', [$this, 'lineCount']),
            new \Twig_SimpleFilter('autoExpand', [$this, 'autoExpand'], ['is_safe' => ['html']]),
            new \Twig_SimpleFilter('base64', [$this, 'base64']),
            new \Twig_SimpleFilter('truncateOutput', [$this, 'truncateOutput']),
            new \Twig_SimpleFilter('parseRunDiff', [$this, 'parseRunDiff'], ['is_safe' => ['html']]),
            new \Twig_SimpleFilter('runDiff', [$this, 'runDiff'], ['is_safe' => ['html']]),
        ];
    }

    public function getGlobals()
    {
        $notify_flag    = (bool)($this->domjudge->getCookie("domjudge_notify"));
        $refresh_cookie = $this->domjudge->getCookie("domjudge_refresh");
        $refresh_flag   = ($refresh_cookie == null || (bool)$refresh_cookie);

        // TODO: use domserver-static.php defines here
        $dir = realpath(sprintf('%s/../../etc', $this->kernel->getRootDir()));
        require_once $dir . '/domserver-config.php';

        // These variables mostly exist for the header template
        return [
            'contest' => $this->domjudge->getCurrentContest(),
            'contests' => $this->domjudge->getCurrentContests(),
            'have_printing' => $this->domjudge->dbconfig_get('enable_printing', 0),
            'notify_flag' => $notify_flag,
            'refresh_flag' => $refresh_flag,
            'icat_url' => defined('ICAT_URL') ? ICAT_URL : null,
            'ext_ccs_url' => defined('EXT_CCS_URL') ? EXT_CCS_URL : null,
        ];
    }

    public function timediff($start, $end = null)
    {
        if (is_null($end)) {
            $end = Utils::now();
        }
        $ret  = '';
        $diff = floor($end - $start);

        if ($diff >= 24 * 60 * 60) {
            $d    = floor($diff / (24 * 60 * 60));
            $ret  .= $d . "d ";
            $diff -= $d * 24 * 60 * 60;
        }
        if ($diff >= 60 * 60 || isset($d)) {
            $h    = floor($diff / (60 * 60));
            $ret  .= $h . ":";
            $diff -= $h * 60 * 60;
        }
        $m    = floor($diff / 60);
        $ret  .= sprintf('%02d:', $m);
        $diff -= $m * 60;
        $ret  .= sprintf('%02d', $diff);

        return $ret;
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
        if ($contest !== null && $this->domjudge->dbconfig_get('show_relative_time', false)) {
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
                $format = $this->domjudge->dbconfig_get('time_format', '%H:%M');
            }
            return Utils::printtime($datetime, $format);
        }
    }

    /**
     * render a button
     * @param string      $url
     * @param string      $text
     * @param string      $type
     * @param string|null $icon
     * @return string
     */
    public function button(string $url, string $text, string $type = 'primary', string $icon = null)
    {
        if ($icon) {
            $icon = sprintf('<i class="fas fa-%s"></i>&nbsp;', $icon);
        }

        return sprintf('<a href="%s" class="btn btn-%s" title="%s">%s%s</a>', $url, $type, $text, $icon, $text);
    }

    /**
     * Output the testcase results for the given submissions
     * @param Submission $submission
     * @return string
     */
    public function testcaseReults(Submission $submission)
    {
        // We use a direct SQL query here for performance reasons
        $judging   = $submission->getJudgings()->first();
        $judgingId = $judging ? $judging->getJudgingid() : null;
        $probId    = $submission->getProbid();
        $testcases = $this->entityManager->getConnection()->fetchAll(
            'SELECT r.runresult, t.rank, t.description
                  FROM testcase t
                  LEFT JOIN judging_run r ON (r.testcaseid = t.testcaseid
                                              AND r.judgingid = :judgingid)
                  WHERE t.probid = :probid ORDER BY rank', [':judgingid' => $judgingId, ':probid' => $probId]);

        $submissionDone = $judging ? !empty($judging->getEndtime()) : false;

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
     * @return string
     */
    public function displayTestcaseResults(array $testcases, bool $submissionDone)
    {
        $results = '';
        foreach ($testcases as $testcase) {
            $class     = $submissionDone ? 'secondary' : 'primary';
            $text      = '?';
            $isCorrect = false;
            $run       = $testcase->getFirstJudgingRun();

            if ($run && $run->getRunresult() !== null) {
                $text  = substr($run->getRunresult(), 0, 1);
                $class = 'danger';
                if ($run->getRunresult() === Judging::RESULT_CORRECT) {
                    $isCorrect = true;
                    $text      = '✓';
                    $class     = 'success';
                }
            }

            $description = $testcase->getDescription(true);


            $extraTitle = '';
            if ($run && $run->getRunresult() !== null) {
                $extraTitle = sprintf(', runtime: %ss, result: %s', $run->getRuntime(), $run->getRunresult());
            }
            $icon    = sprintf('<span class="badge badge-%s badge-testcase">%s</span>', $class, $text);
            $results .= sprintf('<a title="#%d, desc: %s%s" href="#run-%d" %s>%s</a>', $testcase->getRank(),
                                $description, $extraTitle, $testcase->getRank(),
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
     * Return the URL to an external CCS for the given submission if available
     * @param Submission $submission
     * @return string|null
     */
    public function externalCcsUrl(Submission $submission)
    {
        // TODO: use domserver-static.php defines here
        $dir = realpath(sprintf('%s/../../etc', $this->kernel->getRootDir()));
        require_once $dir . '/domserver-config.php';

        if (defined('EXT_CCS_URL') && $submission->getExternalid()) {
            return sprintf('%s%s', EXT_CCS_URL, $submission->getExternalid());
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
     * Show an automatically expanding text
     * @param string|null $text
     * @return string
     */
    public function autoExpand(string $text = null): string
    {
        if ($text == null) {
            return '';
        }
        $descriptionLines = explode("\n", $text);
        if (count($descriptionLines) <= 3) {
            return implode('<br />', $descriptionLines);
        } else {
            $default         = implode('<br />', array_slice($descriptionLines, 0, 3));
            $defaultEscaped  = htmlentities($default);
            $expandedEsacped = htmlentities(implode('<br />', $descriptionLines));
            return <<<EOF
<span>
    <span data-expanded="$expandedEsacped" data-collapsed="$defaultEscaped">
    $default
    </span>
    <br/>
    <a href="javascript:;" onclick="toggleExpand(event)">[expand]</a>
</span>
EOF;
        }
    }

    /**
     * Base64 encode the given input
     * @param string $input
     * @return string
     */
    public function base64(string $input): string
    {
        return base64_encode($input);
    }

    /**
     * Parse the run diff for a given difftext
     * @param string $difftext
     * @return string
     */
    public function parseRunDiff(string $difftext): string
    {
        $line = strtok($difftext, "\n"); //first line
        if (sscanf($line, "### DIFFERENCES FROM LINE %d ###\n", $firstdiff) != 1) {
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

    /**
     * Truncate the given output to the output display limit
     * @param string $output
     * @return string
     * @throws \Exception
     */
    public function truncateOutput(string $output)
    {
        $size = (int)$this->domjudge->dbconfig_get('output_display_limit', 2000);
        // $size == -1 means never perform truncation:
        if ($size < 0) {
            return $output;
        }

        if (strlen($output) > $size) {
            $msg = sprintf("\n[output display truncated after %d B]\n", $size);
            return substr($output, 0, $size) . $msg;
        }
        return $output;
    }

    /**
     * Output a run diff
     * @param JudgingRunWithOutput $run
     * @param Testcase             $testcase
     * @return string
     * @throws \Exception
     */
    public function runDiff(JudgingRunWithOutput $run, Testcase $testcase)
    {
        // TODO: can be improved using diffposition.txt
        // FIXME: only show when diffposition.txt is set?
        // FIXME: cut off after XXX lines
        $testcaseOutput = stream_get_contents($testcase->getTestcaseContent()->getOutput());
        $lines_team     = preg_split('/\n/', trim($this->truncateOutput($run->getOutputRun())));
        $lines_ref      = preg_split('/\n/', trim($this->truncateOutput($testcaseOutput)));

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
}
