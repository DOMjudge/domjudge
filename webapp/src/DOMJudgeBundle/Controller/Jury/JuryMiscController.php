<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\ScoreboardService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use DOMJudgeBundle\Entity\Language;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class JuryMiscController extends Controller
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * GeneralInfoController constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService $DOMJudgeService
     */
    public function __construct(EntityManagerInterface $entityManager, DOMJudgeService $DOMJudgeService)
    {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
    }

    /**
     * @Route("/jury/", name="jury_index")
     * @Security("has_role('ROLE_JURY') or has_role('ROLE_BALLOON')")
     */
    public function indexAction(Request $request)
    {
        $errors = array();
        if ( $this->DOMJudgeService->checkrole('admin') ) {
            $result = $this->entityManager->createQueryBuilder()
                ->select('u.username, u.password')
                ->from('DOMJudgeBundle:User', 'u')
                ->join('u.roles', 'r')
                ->andWhere('r.dj_role = :role')
                ->setParameter('role', 'admin')
                ->getQuery()->getResult();
            foreach ($result as $row) {
                if ( $row['password'] && password_verify($row['username'], $row['password']) ) {
                    $errors[] = "Security alert: the password of the user '"
                        . $row['username'] . "' matches their username. You should change it immediately!";
                }
            }
        }
        return $this->render('DOMJudgeBundle:jury:index.html.twig', ['errors' => $errors]);
    }

    /**
     * @Route("/jury/index.php", name="jury_index_php_redirect")
     */
    public function indexRedirectAction(Request $request)
    {
        return $this->redirectToRoute('jury_index');
    }

    /**
     * @Route("/jury/balloons.php", name="jury_balloons_php_redirect")
     */
    public function balloonsRedirectAction(Request $request)
    {
        return $this->redirectToRoute('jury_balloons');
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
     * @Route("/jury/print.php", methods={"GET"}, name="jury_print_php_redirect")
     */
    public function printRedirectAction(Request $request)
    {
        return $this->redirectToRoute('jury_print');
    }

    /**
     * @Route("/jury/updates", methods={"GET"}, name="jury_ajax_updates")
     * @Security("has_role('ROLE_JURY') or has_role('ROLE_BALLOON')")
     */
    public function updatesAction(Request $request)
    {
        return $this->json($this->DOMJudgeService->getUpdates());
    }

    /**
     * @Route("/jury/updates.php", name="jury_updates_php_redirect")
     */
    public function updatesRedirectAction(Request $request)
    {
        return $this->redirectToRoute('jury_updates');
    }
}
