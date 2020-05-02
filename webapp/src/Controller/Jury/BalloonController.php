<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Entity\Balloon;
use App\Entity\ScoreCache;
use App\Entity\TeamAffiliation;
use App\Service\BalloonService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/balloons")
 * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON')")
 */
class BalloonController extends AbstractController
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * BalloonController constructor.
     *
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param ConfigurationService   $config
     * @param EventLogService        $eventLogService
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
        $this->config          = $config;
        $this->eventLogService = $eventLogService;
    }

    /**
     * @Route("", name="jury_balloons")
     */
    public function indexAction(Request $request, BalloonService $balloonService)
    {

        $contest = $this->dj->getCurrentContest();
        if(is_null($contest)) {
            return $this->render('jury/balloons.html.twig');
        }

        $balloons_table = $balloonService->collectBalloonTable($contest);

        // Add CSS class and actions.
        foreach ($balloons_table as $element) {
            if ($element['data']['done']) {
                $cssclass = 'disabled';
                $balloonactions = [[]];
            } else {
                $cssclass = null;
                $balloonactions = [[
                    'icon' => 'running',
                    'title' => 'mark balloon as done',
                    'link' => $this->generateUrl('jury_balloons_setdone', [
                        'balloonId' => $element['data']['balloonid'],
                    ])]];
            }
            $element['data']['actions'] = $balloonactions;
            $element['data']['cssclass'] = $cssclass;
        }

        // Load preselected filters
        $filters              = $this->dj->jsonDecode((string)$this->dj->getCookie('domjudge_balloonsfilter') ?: '[]');
        $filteredAffiliations = [];
        if (isset($filters['affiliation-id'])) {
            /** @var TeamAffiliation[] $filteredAffiliations */
            $filteredAffiliations = $this->em->createQueryBuilder()
                ->from(TeamAffiliation::class, 'a')
                ->select('a')
                ->where('a.affilid IN (:affilIds)')
                ->setParameter(':affilIds', $filters['affiliation-id'])
                ->getQuery()
                ->getResult();
        }

        return $this->render('jury/balloons.html.twig', [
            'refresh' => [
                'after' => 60,
                'url' => $this->generateUrl('jury_balloons'),
                'ajax' => true
            ],
            'isfrozen' => isset($contest->getState()['frozen']),
            'hasFilters' => !empty($filters),
            'filteredAffiliations' => $filteredAffiliations,
            'balloons' => $balloons_table
        ]);
    }

    /**
     * @Route("/{balloonId}/done", name="jury_balloons_setdone")
     */
    public function setDoneAction(Request $request, int $balloonId)
    {
        $em = $this->em;
        $balloon = $em->getRepository(Balloon::class)->find($balloonId);
        if (!$balloon) {
            throw new NotFoundHttpException('balloon not found');
        }
        $balloon->setDone(true);
        $em->flush();

        return $this->redirectToRoute("jury_balloons");
    }
}
