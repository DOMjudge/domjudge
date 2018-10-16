<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use DOMJudgeBundle\Entity\Team;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class JuryMiscController extends Controller
{
    /**
     * @Route("/jury/", name="jury_index")
     * @Security("has_role('ROLE_JURY') or has_role('ROLE_BALLOON')")
     */
    public function indexAction(Request $request)
    {
        // TODO: make sure both jury and balloon user can access this
        return $this->render('DOMJudgeBundle:jury:index.html.twig', []);
    }

    /**
     * @Route("/jury/index.php", name="jury_index_php_redirect")
     */
    public function indexRedirectAction(Request $request)
    {
        return $this->redirectToRoute('jury_index');
    }
}
