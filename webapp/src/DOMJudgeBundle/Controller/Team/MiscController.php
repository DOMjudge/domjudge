<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Team;

use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Form\PrintType;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Utils\Printing;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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

    /**
     * @Route("/print", name="team_print")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function printAction(Request $request)
    {
        if ( ! $this->DOMJudgeService->dbconfig_get('enable_printing', 0) ) {
            throw new AccessDeniedHttpException("Printing disabled in config");
        }

        $form = $this->createForm(PrintType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            /** @var UploadedFile $file */
            $file = $data['code'];
            $realfile = $file->getRealPath();
            $originalfilename = $file->getClientOriginalName()??'';

            $langid = $data['langid'];
            $username = $this->getUser()->getUsername();

            $team = $this->DOMJudgeService->getUser()->getTeam();
            $ret = Printing::send($realfile, $originalfilename, $langid, $username, $team->getName(), $team->getRoom());

            return $this->render('@DOMJudge/team/print_result.html.twig', [
                'success' => $ret[0], 'output' => $ret[1],
            ]);
        }

        return $this->render('@DOMJudge/team/print.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
