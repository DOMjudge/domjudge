<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

class ExecutableController extends Controller
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
     * @param DOMJudgeService        $DOMJudgeService
     */
    public function __construct(EntityManagerInterface $entityManager, DOMJudgeService $DOMJudgeService)
    {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
    }

    /**
     * @Route("/jury/executables/", name="jury_executables")
     * @Security("has_role('ROLE_JURY')")
     */
    public function indexAction(Request $request, Packages $assetPackage)
    {
        // $res = $DB->q('SELECT execid, description, md5sum, type, OCTET_LENGTH(zipfile) AS size
        //                FROM executable ORDER BY execid');
        $em               = $this->entityManager;
        $executables      = $em->createQueryBuilder()
            ->select('e as executable, e.execid as execid, length(e.zipfile) as size')
            ->from('DOMJudgeBundle:Executable', 'e')
            ->orderBy('e.execid', 'ASC')
            ->getQuery()->getResult();
        $executable_sizes = array_column($executables, 'size', 'execid');
        $executables      = array_column($executables, 'executable', 'execid');
        $table_fields     = [
            'execid' => ['title' => 'ID', 'sort' => true,],
            'type' => ['title' => 'type', 'sort' => true,],
            'description' => ['title' => 'description', 'sort' => true, 'default_sort' => true],
            'size' => ['title' => 'size', 'sort' => true,],
            'md5sum' => ['title' => 'md5', 'sort' => true, 'search' => false],
        ];
        if ($this->isGranted('ROLE_ADMIN')) {
            $table_fields = array_merge($table_fields, [
                'save' => ['title' => '', 'sort' => false,],
                'edit' => ['title' => '', 'sort' => false,],
                'delete' => ['title' => '', 'sort' => false,],
            ]);
        }

        $propertyAccessor  = PropertyAccess::createPropertyAccessor();
        $executables_table = [];
        foreach ($executables as $e) {
            $execdata = [];
            // Get whatever fields we can from the team object itself
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($e, $k)) {
                    $execdata[$k] = ['value' => $propertyAccessor->getValue($e, $k)];
                }
            }


            $editvalue   = '<i class="fas fa-edit" title="edit this executable"></i>';
            $editlink    = $this->generateUrl('legacy.jury_executable', [
                'cmd' => 'edit',
                'id' => $e->getExecid(),
                'referrer' => 'executables/'
            ]);
            $deletevalue = '<i class="fas fa-trash-alt" title="delete this executable"></i>';
            $deletelink  = $this->generateUrl('legacy.jury_delete', [
                'table' => 'executable',
                'execid' => $e->getExecid(),
                'referrer' => '',
                'desc' => $e->getDescription(),
            ]);
            $savevalue   = '<i class="fas fa-file-download" title="download this executable"></i>';
            $savelink    = $this->generateUrl('legacy.jury_executable', [
                'fetch' => '',
                'id' => $e->getExecid(),
            ]);

            $execdata['md5sum']['cssclass'] = 'text-monospace small';
            $execdata                       = array_merge($execdata, [
                'size' => ['value' => Utils::printsize((int)$executable_sizes[$e->getExecid()])],
                'save' => ['value' => $savevalue, 'link' => $savelink],
                'edit' => ['value' => $editvalue, 'link' => $editlink],
                'delete' => ['value' => $deletevalue, 'link' => $deletelink],
            ]);
            $executables_table[]            = [
                'data' => $execdata,
                'link' => $this->generateUrl('legacy.jury_executable', ['id' => $e->getExecid()]),
            ];
        }
        return $this->render('@DOMJudge/jury/executables.html.twig', [
            'executables' => $executables_table,
            'table_fields' => $table_fields,
        ]);
    }

    /**
     * @Route("/jury/executables.php", name="jury_executables_php_redirect")
     */
    public function executablesRedirectAction(Request $request)
    {
        return $this->redirectToRoute('jury_executables');
    }
}
