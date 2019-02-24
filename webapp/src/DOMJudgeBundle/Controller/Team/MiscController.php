<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Team;

use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Service\DOMJudgeService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class MiscController
 *
 * @Route("/team")
 * @Security("is_granted('ROLE_TEAM')")
 * @Security("user.getTeam() !== null", message="You do not have a team associated with your account. ")
 *
 * @package DOMJudgeBundle\Controller\Team
 */
class MiscController extends BaseController
{
    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * MiscController constructor.
     * @param DOMJudgeService $DOMJudgeService
     */
    public function __construct(DOMJudgeService $DOMJudgeService)
    {
        $this->DOMJudgeService = $DOMJudgeService;
    }

    /**
     * @Route("/change-contest/{contestId}", name="team_change_contest")
     * @param Request         $request
     * @param RouterInterface $router
     * @param int             $contestId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function changeContestAction(Request $request, RouterInterface $router, int $contestId)
    {
        if ($this->isLocalReferrer($router, $request)) {
            $response = new RedirectResponse($request->headers->get('referer'));
        } else {
            $response = $this->redirectToRoute('public_index');
        }
        return $this->DOMJudgeService->setCookie('domjudge_cid', (string)$contestId, 0, null, '', false, false,
                                                 $response);
    }
}
