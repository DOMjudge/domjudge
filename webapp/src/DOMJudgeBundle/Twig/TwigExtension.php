<?php declare(strict_types=1);

namespace DOMJudgeBundle\Twig;

use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Utils\Utils;
use Twig\TwigFunction;

class TwigExtension extends \Twig_Extension implements \Twig_Extension_GlobalsInterface
{
    protected $domjudge;

    public function __construct(DOMJudgeService $domjudge)
    {
        $this->domjudge = $domjudge;
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
        ];
    }

    public function getGlobals()
    {
        $notify_flag    = (bool)($this->domjudge->getCookie("domjudge_notify"));
        $refresh_cookie = $this->domjudge->getCookie("domjudge_refresh");
        $refresh_flag   = ($refresh_cookie == null || (bool)$refresh_cookie);

        // This is for various notifications
        // e.g. judgehost down, rejudging active, clarifications, internal errors
        // TODO: we should only bother doing this if jury
        $updates = $this->domjudge->getUpdates();

        // These variables mostly exist for the header template
        return [
            'contest' => $this->domjudge->getCurrentContest(),
            'contests' => $this->domjudge->getCurrentContests(),
            'have_printing' => $this->domjudge->dbconfig_get('enable_printing', 0),
            'notify_flag' => $notify_flag,
            'refresh_flag' => $refresh_flag,

            // Jury Specific
            'updates' => $updates,
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
     * @param string       $format
     * @return string
     */
    public function printtime($datetime, string $format): string
    {
        return Utils::printtime($datetime, $format);
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
}
