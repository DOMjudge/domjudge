<?php declare(strict_types=1);
namespace DOMJudgeBundle\Twig;

use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Utils\Utils;

class TwigExtension extends \Twig_Extension implements \Twig_Extension_GlobalsInterface
{
    protected $domjudge;
    public function __construct(DOMJudgeService $domjudge)
    {
        $this->domjudge = $domjudge;
    }

    public function getFunctions()
    {
        return array();
    }
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('timediff', array($this, 'timediff')),
        );
    }
    public function getGlobals()
    {
        $notify_flag = (bool)($this->domjudge->getCookie("domjudge_notify"));
        $refresh_cookie = $this->domjudge->getCookie("domjudge_refresh");
        $refresh_flag = ($refresh_cookie == null || (bool)$refresh_cookie);

        // This is for various notifications
        // e.g. judgehost down, rejudging active, clarifications, internal errors
        // TODO: we should only bother doing this if jury
        $updates = $this->domjudge->getUpdates();

        // These variables mostly exist for the header template
        return array(
            'contest' => $this->domjudge->getCurrentContest(),
            'contests' => $this->domjudge->getCurrentContests(),
            'have_printing' => $this->domjudge->dbconfig_get('enable_printing', 0),
            'notify_flag' => $notify_flag,
            'refresh_flag' => $refresh_flag,

            // Jury Specific
            'updates' => $updates,
        );
    }

    public function timediff($start, $end = null)
    {
        if (is_null($end)) {
            $end = Utils::now();
        }
        $ret = '';
        $diff = floor($end - $start);

        if ($diff >= 24*60*60) {
            $d = floor($diff/(24*60*60));
            $ret .= $d . "d ";
            $diff -= $d * 24*60*60;
        }
        if ($diff >= 60*60 || isset($d)) {
            $h = floor($diff/(60*60));
            $ret .= $h . ":";
            $diff -= $h * 60*60;
        }
        $m = floor($diff/60);
        $ret .= sprintf('%02d:', $m);
        $diff -= $m * 60;
        $ret .= sprintf('%02d', $diff);

        return $ret;
    }
}
