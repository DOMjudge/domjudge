<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller;

use DOMJudgeBundle\Service\DOMJudgeService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use DOMJudgeBundle\Entity\Language;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class JuryMiscController extends Controller
{
    /**
     * @var DOMJudgeService
     */
    private $DOMJudgeService;

    public function __construct(DOMJudgeService $DOMJudgeService)
    {
        $this->DOMJudgeService = $DOMJudgeService;
    }

    /**
     * @Route("/jury/", name="jury_index")
     * @Security("has_role('ROLE_JURY') or has_role('ROLE_BALLOON')")
     */
    public function indexAction(Request $request)
    {
        return $this->render('DOMJudgeBundle:jury:index.html.twig', []);
    }

    /**
     * @Route("/jury/index.php", name="jury_index_php_redirect")
     */
    public function indexRedirectAction(Request $request)
    {
        return $this->redirectToRoute('jury_index');
    }

    /**
     * @Route("/jury/print", methods={"GET"}, name="jury_print")
     * @Security("has_role('ROLE_JURY') or has_role('ROLE_BALLOON')")
     */
    public function printShowAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $langs = $em->getRepository('DOMJudgeBundle:Language')->findAll();
        $langlist = [];
        foreach ($langs as $lang) {
            $langlist[$lang->getLangid()] = $lang->getName();
        }
        asort($langlist);
        return $this->render('DOMJudgeBundle:jury:print.html.twig', ['langlist' => $langlist]);
    }

    /**
     * @Route("/jury/updates", methods={"GET"}, name="jury_ajax_updates")
     * @Security("has_role('ROLE_JURY') or has_role('ROLE_BALLOON')")
     */
    public function updatesAction(Request $request)
    {
	return $this->json($this->DOMJudgeService->getUpdates());
    }
}
