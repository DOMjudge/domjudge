<?php declare(strict_types=1);

namespace DOMJudgeBundle\Twig;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Judging;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Utils\Utils;
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

    public function __construct(DOMJudgeService $domjudge, EntityManagerInterface $entityManager)
    {
        $this->domjudge      = $domjudge;
        $this->entityManager = $entityManager;
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
            new \Twig_SimpleFilter('testcaseReults', [$this, 'testcaseReults'], ['is_safe' => ['html']]),
        ];
    }

    public function getGlobals()
    {
        $notify_flag    = (bool)($this->domjudge->getCookie("domjudge_notify"));
        $refresh_cookie = $this->domjudge->getCookie("domjudge_refresh");
        $refresh_flag   = ($refresh_cookie == null || (bool)$refresh_cookie);

        // These variables mostly exist for the header template
        return [
            'contest' => $this->domjudge->getCurrentContest(),
            'contests' => $this->domjudge->getCurrentContests(),
            'have_printing' => $this->domjudge->dbconfig_get('enable_printing', 0),
            'notify_flag' => $notify_flag,
            'refresh_flag' => $refresh_flag,
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

        $submissionDone = $judging ? !empty($judging->getResult()) : false;

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
}
