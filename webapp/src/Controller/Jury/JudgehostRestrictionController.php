<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\JudgehostRestriction;
use App\Entity\Language;
use App\Entity\Problem;
use App\Form\Type\JudgehostRestrictionType;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/judgehost-restrictions")
 * @IsGranted("ROLE_JURY")
 */
class JudgehostRestrictionController extends BaseController
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
     * @var EventLogService
     */
    protected $eventLog;

    /**
     * @var KernelInterface
     */
    protected $kernel;

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $dj,
        EventLogService $eventLog,
        KernelInterface $kernel
    ) {
        $this->em       = $entityManager;
        $this->dj       = $dj;
        $this->eventLog = $eventLog;
        $this->kernel   = $kernel;
    }

    /**
     * @Route("", name="jury_judgehost_restrictions")
     */
    public function indexAction(Request $request): Response
    {
        /** @var JudgehostRestriction[] $judgehostRestrictions */
        $judgehostRestrictions = $this->em->createQueryBuilder()
            ->from(JudgehostRestriction::class, 'jr')
            ->select('jr')
            ->orderBy('jr.restrictionid')
            ->getQuery()->getResult();

        $table_fields = [
            'restrictionid' => ['title' => 'ID', 'sort' => true, 'default_sort' => true],
            'name' => ['title' => 'name', 'sort' => true],
            'numcontests' => ['title' => '# contests', 'sort' => true],
            'numproblems' => ['title' => '# problems', 'sort' => true],
            'numlanguages' => ['title' => '# languages', 'sort' => true],
            'numlinkedjudgehosts' => ['title' => '# linked judgehosts', 'sort' => true],
        ];

        $propertyAccessor             = PropertyAccess::createPropertyAccessor();
        $judgehost_restrictions_table = [];
        foreach ($judgehostRestrictions as $judgehostRestriction) {
            $judgehostrestrictiondata    = [];
            $judgehostrestrictionactions = [];
            // Get whatever fields we can from the problem object itself
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($judgehostRestriction, $k)) {
                    $judgehostrestrictiondata[$k] = ['value' => $propertyAccessor->getValue($judgehostRestriction, $k)];
                }
            }

            $judgehostrestrictiondata = array_merge($judgehostrestrictiondata, [
                'numcontests' => ['value' => count($judgehostRestriction->getContests())],
                'numproblems' => ['value' => count($judgehostRestriction->getProblems())],
                'numlanguages' => ['value' => count($judgehostRestriction->getLanguages())],
                'numlinkedjudgehosts' => ['value' => $judgehostRestriction->getJudgehosts()->count()],
            ]);

            // Create action links
            if ($this->isGranted('ROLE_ADMIN')) {
                $judgehostrestrictionactions[] = [
                    'icon' => 'edit',
                    'title' => 'edit this judgehost restriction',
                    'link' => $this->generateUrl('jury_judgehost_restriction_edit', [
                        'restrictionId' => $judgehostRestriction->getRestrictionid(),
                    ])
                ];
                $judgehostrestrictionactions[] = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this judgehost restriction',
                    'link' => $this->generateUrl('jury_judgehost_restriction_delete', [
                        'restrictionId' => $judgehostRestriction->getRestrictionid(),
                    ]),
                    'ajaxModal' => true,
                ];
            }

            // Save this to our list of rows
            $judgehost_restrictions_table[] = [
                'data' => $judgehostrestrictiondata,
                'actions' => $judgehostrestrictionactions,
                'link' => $this->generateUrl('jury_judgehost_restriction',
                                             ['restrictionId' => $judgehostRestriction->getRestrictionid()]),
            ];
        }


        return $this->render('jury/judgehost_restrictions.html.twig', [
            'judgehost_restrictions' => $judgehost_restrictions_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 2 : 0,
        ]);
    }

    /**
     * @Route("/{restrictionId<\d+>}", name="jury_judgehost_restriction")
     * @throws NonUniqueResultException
     */
    public function viewAction(int $restrictionId): Response
    {
        /** @var JudgehostRestriction $judgehostRestriction */
        $judgehostRestriction = $this->em->createQueryBuilder()
            ->from(JudgehostRestriction::class, 'jr')
            ->leftJoin('jr.judgehosts', 'j')
            ->select('jr', 'j')
            ->andWhere('jr.restrictionid = :restrictionId')
            ->setParameter(':restrictionId', $restrictionId)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$judgehostRestriction) {
            throw new NotFoundHttpException(sprintf('Judgehost restriction with ID %s not found', $restrictionId));
        }

        /** @var Contest[] $contests */
        $contests = $this->em->createQueryBuilder()
            ->from(Contest::class, 'c', 'c.cid')
            ->select('c')
            ->getQuery()
            ->getResult();

        /** @var Problem[] $problems */
        $problems = $this->em->createQueryBuilder()
            ->from(Problem::class, 'p', 'p.probid')
            ->select('p')
            ->getQuery()
            ->getResult();

        /** @var Language[] $languages */
        $languages = $this->em->createQueryBuilder()
            ->from(Language::class, 'l', 'l.langid')
            ->select('l')
            ->getQuery()
            ->getResult();

        return $this->render('jury/judgehost_restriction.html.twig', [
            'judgehostRestriction' => $judgehostRestriction,
            'contests' => $contests,
            'problems' => $problems,
            'languages' => $languages,
        ]);
    }

    /**
     * @Route("/{restrictionId<\d+>}/edit", name="jury_judgehost_restriction_edit")
     * @IsGranted("ROLE_ADMIN")
     */
    public function editAction(Request $request, int $restrictionId): Response
    {
        /** @var JudgehostRestriction $judgehostRestriction */
        $judgehostRestriction = $this->em->getRepository(JudgehostRestriction::class)->find($restrictionId);

        $form = $this->createForm(JudgehostRestrictionType::class, $judgehostRestriction);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->dj->auditlog('judgehost_restriction', $judgehostRestriction->getRestrictionid(),
                                             'updated');
            return $this->redirect($this->generateUrl(
                'jury_judgehost_restriction',
                ['restrictionId' => $judgehostRestriction->getRestrictionid()]
            ));
        }

        return $this->render('jury/judgehost_restriction_edit.html.twig', [
            'judgehostRestriction' => $judgehostRestriction,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{restrictionId<\d+>}/delete", name="jury_judgehost_restriction_delete")
     * @IsGranted("ROLE_ADMIN")
     * @throws DBALException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function deleteAction(Request $request, int $restrictionId): Response
    {
        /** @var JudgehostRestriction $judgehostRestriction */
        $judgehostRestriction = $this->em->getRepository(JudgehostRestriction::class)->find($restrictionId);

        return $this->deleteEntities($request, $this->em, $this->dj, $this->eventLog, $this->kernel,
                                     [$judgehostRestriction], $this->generateUrl('jury_judgehost_restrictions'));
    }

    /**
     * @Route("/add", name="jury_judgehost_restriction_add")
     * @IsGranted("ROLE_ADMIN")
     */
    public function addAction(Request $request): Response
    {
        $judgehostRestriction = new JudgehostRestriction();

        $form = $this->createForm(JudgehostRestrictionType::class, $judgehostRestriction);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($judgehostRestriction);
            $this->em->flush();
            $this->dj->auditlog('judgehost_restriction', $judgehostRestriction->getRestrictionid(),
                                             'added');
            return $this->redirect($this->generateUrl(
                'jury_judgehost_restriction',
                ['restrictionId' => $judgehostRestriction->getRestrictionid()]
            ));
        }

        return $this->render('jury/judgehost_restriction_add.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
