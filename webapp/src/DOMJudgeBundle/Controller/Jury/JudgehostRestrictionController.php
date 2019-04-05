<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\JudgehostRestriction;
use DOMJudgeBundle\Entity\Language;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Form\Type\JudgehostRestrictionType;
use DOMJudgeBundle\Service\DOMJudgeService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/judgehost-restrictions")
 * @Security("has_role('ROLE_JURY')")
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

    public function __construct(EntityManagerInterface $entityManager, DOMJudgeService $dj)
    {
        $this->em = $entityManager;
        $this->dj = $dj;
    }

    /**
     * @Route("", name="jury_judgehost_restrictions")
     */
    public function indexAction(Request $request)
    {
        /** @var JudgehostRestriction[] $judgehostRestrictions */
        $judgehostRestrictions = $this->em->createQueryBuilder()
            ->from('DOMJudgeBundle:JudgehostRestriction', 'jr')
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


        return $this->render('@DOMJudge/jury/judgehost_restrictions.html.twig', [
            'judgehost_restrictions' => $judgehost_restrictions_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 2 : 0,
        ]);
    }

    /**
     * @Route("/{restrictionId}", name="jury_judgehost_restriction",
     *                                                   requirements={"restrictionId": "\d+"})
     * @param int $restrictionId
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function viewAction(int $restrictionId)
    {
        /** @var JudgehostRestriction $judgehostRestriction */
        $judgehostRestriction = $this->em->createQueryBuilder()
            ->from('DOMJudgeBundle:JudgehostRestriction', 'jr')
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
            ->from('DOMJudgeBundle:Contest', 'c', 'c.cid')
            ->select('c')
            ->getQuery()
            ->getResult();

        /** @var Problem[] $problems */
        $problems = $this->em->createQueryBuilder()
            ->from('DOMJudgeBundle:Problem', 'p', 'p.probid')
            ->select('p')
            ->getQuery()
            ->getResult();

        /** @var Language[] $languages */
        $languages = $this->em->createQueryBuilder()
            ->from('DOMJudgeBundle:Language', 'l', 'l.langid')
            ->select('l')
            ->getQuery()
            ->getResult();

        return $this->render('@DOMJudge/jury/judgehost_restriction.html.twig', [
            'judgehostRestriction' => $judgehostRestriction,
            'contests' => $contests,
            'problems' => $problems,
            'languages' => $languages,
        ]);
    }

    /**
     * @Route("/{restrictionId}/edit", name="jury_judgehost_restriction_edit")
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @param int     $restrictionId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editAction(Request $request, int $restrictionId)
    {
        /** @var JudgehostRestriction $judgehostRestriction */
        $judgehostRestriction = $this->em->getRepository(JudgehostRestriction::class)->find($restrictionId);

        $form = $this->createForm(JudgehostRestrictionType::class, $judgehostRestriction);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->dj->auditlog('judgehost_restriction', $judgehostRestriction->getRestrictionid(),
                                             'updated');
            return $this->redirect($this->generateUrl('jury_judgehost_restriction',
                                                      ['restrictionId' => $judgehostRestriction->getRestrictionid()]));
        }

        return $this->render('@DOMJudge/jury/judgehost_restriction_edit.html.twig', [
            'judgehostRestriction' => $judgehostRestriction,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{restrictionId}/delete", name="jury_judgehost_restriction_delete")
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @param int     $restrictionId
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function deleteAction(Request $request, int $restrictionId)
    {
        /** @var JudgehostRestriction $judgehostRestriction */
        $judgehostRestriction = $this->em->getRepository(JudgehostRestriction::class)->find($restrictionId);

        return $this->deleteEntity($request, $this->em, $this->dj, $judgehostRestriction, $judgehostRestriction->getName(), $this->generateUrl('jury_judgehost_restrictions'));
    }

    /**
     * @Route("/add", name="jury_judgehost_restriction_add")
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addAction(Request $request)
    {
        $judgehostRestriction = new JudgehostRestriction();

        $form = $this->createForm(JudgehostRestrictionType::class, $judgehostRestriction);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($judgehostRestriction);
            $this->em->flush();
            $this->dj->auditlog('judgehost_restriction', $judgehostRestriction->getRestrictionid(),
                                             'added');
            return $this->redirect($this->generateUrl('jury_judgehost_restriction',
                                                      ['restrictionId' => $judgehostRestriction->getRestrictionid()]));
        }

        return $this->render('@DOMJudge/jury/judgehost_restriction_add.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
