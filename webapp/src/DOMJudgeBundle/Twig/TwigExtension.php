<?php
namespace DOMJudgeBundle\Twig;

use DOMJudgeBundle\Service\DOMJudgeService;

class TwigExtension extends \Twig_Extension implements \Twig_Extension_GlobalsInterface
{
    protected $domjudge;
    public function __construct(DOMJudgeService $domjudge)
    {
        $this->domjudge = $domjudge;
    }

    public function getFunctions()
    {
        return array(
            new \Twig_Function('putClock', array($this, 'putClock')),
            new \Twig_Function('checkrole', array($this, 'checkrole')),
        );
    }
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('timediff', array($this, 'timediff')),
        );
    }
    public function getGlobals()
    {
        // TODO: populate these values properly
        // They're used by the header template
        return array(
            // TODO: this should take into account what contest the user selected
            'contest' => $this->domjudge->getCurrentContest(),
            'contests' => $this->domjudge->getCurrentContests(),
            'have_printing' => false,
            'updates' => array(
                'judgehosts' => array(),
                'internal_error' => array(),
                'clarifications' => array(),
                'rejudgings' => array(),
            ),
        );
    }

    public function timediff($start, $end = null)
    {
        if (is_null($end)) {
            $end = microtime(true);
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
