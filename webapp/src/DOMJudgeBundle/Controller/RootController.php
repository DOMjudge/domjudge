<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller;

use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Service\DOMJudgeService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class RootController
 *
 * @Route("")
 *
 * @package DOMJudgeBundle\Controller
 */
class RootController extends BaseController
{
    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * RootController constructor.
     * @param DOMJudgeService        $DOMJudgeService
     */
    public function __construct(DOMJudgeService $DOMJudgeService)
    {
        $this->DOMJudgeService = $DOMJudgeService;
    }

    /**
     * @Route("", name="root")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirectAction(Request $request)
    {
        if ( $this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY') ) {
            if ( $this->DOMJudgeService->checkrole('jury') ) {
                return $this->redirectToRoute('jury_index');
            }
            if ( $this->DOMJudgeService->checkrole('team', false) ) {
                return $this->redirectToRoute('legacy.team_index');
            }
            if ( $this->DOMJudgeService->checkrole('balloon') ) {
                return $this->redirectToRoute('jury_balloons');
            }
        }
        return $this->redirectToRoute('legacy.public');
    }
}
