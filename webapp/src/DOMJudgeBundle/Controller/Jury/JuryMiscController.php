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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
     * @Route("/jury/ajax/{datatype}", methods={"GET"}, name="jury_ajax_data")
     * @param string $datatype
     * @Security("has_role('ROLE_JURY')")
     */
    public function ajaxDataAction(Request $request, string $datatype)
    {
        $q = $request->query->get('q');
        $qb = $this->entityManager->createQueryBuilder();

        if ($datatype === 'problems') {
            $problems = $qb->from('DOMJudgeBundle:Problem', 'p')
                ->select('p.probid', 'p.name')
                ->where($qb->expr()->like('p.name', '?1'))
                ->orWhere($qb->expr()->eq('p.probid', '?2'))
                ->orderBy('p.name', 'ASC')
                ->getQuery()->setParameter(1, '%' . $q . '%')
                ->setParameter(2, $q)
                ->getResult();

            $results = array_map(function (array $problem) {
                $displayname = $problem['name'] . " (p" . $problem['probid'] .")";
                return [
                    'id' => $problem['probid'],
                    'text' => $displayname,
                    'search' => $displayname,
                ];
            }, $problems);
        } elseif ( $datatype === 'teams' ) {
            $teams = $qb->from('DOMJudgeBundle:Team', 't')
                ->select('t.teamid', 't.name')
                ->where($qb->expr()->like('t.name', '?1'))
                ->orWhere($qb->expr()->eq('t.teamid', '?2'))
                ->orderBy('t.name', 'ASC')
                ->getQuery()->setParameter(1, '%' . $q . '%')
                ->setParameter(2, $q)
                ->getResult();

            $results = array_map(function (array $team) {
                $displayname = $team['name'] . " (t" . $team['teamid'] .")";
                return [
                    'id' => $team['teamid'],
                    'text' => $displayname,
                    'search' => $displayname,
                ];
            }, $teams);
        } elseif ( $datatype === 'languages' ) {
            $languages = $qb->from('DOMJudgeBundle:Language', 'l')
                ->select('l.langid', 'l.name')
                ->where($qb->expr()->like('l.name', '?1'))
                ->orWhere($qb->expr()->eq('l.langid', '?2'))
                ->orderBy('l.name', 'ASC')
                ->getQuery()->setParameter(1, '%' . $q . '%')
                ->setParameter(2, $q)
                ->getResult();

            $results = array_map(function (array $language) {
                $displayname = $language['name'] . " (" . $language['langid'] .")";
                return [
                    'id' => $language['langid'],
                    'text' => $displayname,
                    'search' => $displayname,
                ];
            }, $languages);
        } elseif ( $datatype === 'contests' ) {
            $query = $qb->from('DOMJudgeBundle:Contest', 'c')
                ->select('c.cid', 'c.name', 'c.shortname')
                ->where($qb->expr()->like('c.name', '?1'))
                ->orWhere($qb->expr()->like('c.shortname', '?1'))
                ->orWhere($qb->expr()->eq('c.cid', '?2'))
		->orderBy('c.name', 'ASC');

            if ( $request->query->get('public') !== null ) {
                $query = $query->andWhere($qb->expr()->eq('c.public', '?3'));
            }
	    $query = $query->getQuery()
                ->setParameter(1, '%' . $q . '%')
                ->setParameter(2, $q);
           if ( $request->query->get('public') !== null ) {
                $query = $query->setParameter(3, $request->query->get('public'));
           }
           $contests = $query->getResult();

           $results = array_map(function (array $contest) {
               $displayname = $contest['name'] . " (" .$contest['shortname'] . " - c". $contest['cid'] .")";
               return [
                   'id' => $contest['cid'],
                   'text' => $displayname,
                   'search' => $displayname,
               ];
            }, $contests);
        } else {
            throw new NotFoundHttpException("Unknown AJAX data type: " . $datatype);
        }

        // TODO: remove this branch and setting of 'search' above when we use select2 exclusively
        if ($request->query->get('select2') ?? false) {
            return $this->json(['results' => $results]);
        } else {
            return $this->json($results);
        }
    }
}
