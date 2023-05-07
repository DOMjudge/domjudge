<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Doctrine\DBAL\Types\JudgeTaskType;
use App\Entity\Judgehost;
use App\Entity\JudgeTask;
use App\Entity\Judging;
use App\Form\Type\JudgehostsType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * @Route("/jury/judgehosts")
 * @IsGranted("ROLE_JURY")
 */
class JudgehostController extends BaseController
{
    // Note: when adding or modifying routes, make sure they do not clash with the /judgehosts/{hostname} route.

    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected EventLogService $eventLog;
    protected KernelInterface $kernel;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLog,
        KernelInterface $kernel
    ) {
        $this->em       = $em;
        $this->dj       = $dj;
        $this->config   = $config;
        $this->eventLog = $eventLog;
        $this->kernel   = $kernel;
    }

    /**
     * @Route("", name="jury_judgehosts")
     */
    public function indexAction(Request $request): Response
    {
        /** @var Judgehost[] $judgehosts */
        $judgehosts = $this->em->createQueryBuilder()
            ->from(Judgehost::class, 'j')
            ->select('j')
            ->andWhere('j.hidden = 0')
            ->getQuery()->getResult();

        $table_fields = [
            'judgehostid' => ['title' => 'ID'],
            'hostname' => ['title' => 'hostname'],
            'enabled' => ['title' => 'enabled'],
            'status' => ['title' => 'status'],
            'last_judgingid' => ['title' => 'last judging'],
        ];

        $now = Utils::now();

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $time_warn = $this->config->get('judgehost_warning');
        $time_crit = $this->config->get('judgehost_critical');
        $judgehosts_table = [];
        $all_checked_in_recently = true;
        foreach ($judgehosts as $judgehost) {
            $judgehostdata    = [];
            $judgehostactions = [];
            // Get whatever fields we can from the problem object itself.
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($judgehost, $k)) {
                    $judgehostdata[$k] = ['value' => $propertyAccessor->getValue($judgehost, $k)];
                }
            }

            // render hostname nicely
            if ($judgehostdata['hostname']['value']) {
                $judgehostdata['hostname']['value'] = Utils::printhost($judgehostdata['hostname']['value']);
            }
            $judgehostdata['hostname']['default']  = '-';
            $judgehostdata['hostname']['cssclass'] = 'text-monospace small';

            if (empty($judgehost->getPolltime())) {
                $status = 'noconn';
                $statustitle = "never checked in";
                $all_checked_in_recently = false;
            } else {
                $relTime = floor(Utils::difftime($now, (float)$judgehost->getPolltime()));
                if ($relTime < $time_warn) {
                    $status = 'ok';
                } elseif ($relTime < $time_crit) {
                    $status = 'warn';
                } else {
                    $status = 'crit';
                    $all_checked_in_recently = false;
                }
                $statustitle = sprintf('last checked in %ss ago',
                                       Utils::printtimediff((float)$judgehost->getPolltime()));
            }

            $lastJobId = $this->em->createQueryBuilder()
                ->from(JudgeTask::class, 'jt')
                ->select('jt.jobid')
                ->andWhere('jt.judgehost = :judgehost')
                ->andWhere('jt.type = :type')
                ->setParameter('judgehost', $judgehost)
                ->setParameter('type', JudgeTaskType::JUDGING_RUN)
                ->orderBy('jt.starttime', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            $judgehostdata['last_judgingid'] = $lastJobId === null ? null : [
                'value' => 'j' . $lastJobId['jobid'],
            ];

            $judgehostdata = array_merge($judgehostdata, [
                'status' => [
                    'value' => $status,
                    'title' => $statustitle,
                ],
                'enabled' => [
                    'value' => $judgehost->getEnabled() ? 'yes' : 'no',
                ],
            ]);

            // Create action links
            if ($this->isGranted('ROLE_ADMIN')) {
                if ($judgehost->getEnabled()) {
                    $enableicon = 'pause';
                    $enablecmd  = 'disable';
                    $route      = 'jury_judgehost_disable';
                } else {
                    $enableicon = 'play';
                    $enablecmd  = 'enable';
                    $route      = 'jury_judgehost_enable';
                }
                $judgehostactions[] = [
                    'icon' => $enableicon,
                    'title' => sprintf('%s judgehost', $enablecmd),
                    'link' => $this->generateUrl($route, ['judgehostid' => $judgehost->getJudgehostid()]),
                ];

                $judgehostactions[] = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this judgehost',
                    'link' => $this->generateUrl('jury_judgehost_delete', [
                        'judgehostid' => $judgehost->getJudgehostid(),
                    ]),
                    'ajaxModal' => true,
                ];
            }

            // Save this to our list of rows.
            $judgehosts_table[] = [
                'data' => $judgehostdata,
                'actions' => $judgehostactions,
                'link' => $this->generateUrl('jury_judgehost', ['judgehostid' => $judgehost->getJudgehostid()]),
                'cssclass' => $judgehost->getEnabled() ? '' : 'disabled',
            ];
        }

        usort($judgehosts_table, function (array $a, array $b) {
            return strnatcasecmp($a['data']['hostname']['value'], $b['data']['hostname']['value']);
        });

        $data = [
            'judgehosts' => $judgehosts_table,
            'table_fields' => $table_fields,
            'all_checked_in_recently' => $all_checked_in_recently,
            'refresh' => [
                'after' => 5,
                'url' => $this->generateUrl('jury_judgehosts'),
                'ajax' => true,
            ]
        ];
        if ($request->isXmlHttpRequest()) {
            return $this->render('jury/partials/judgehost_list.html.twig', $data);
        } else {
            return $this->render('jury/judgehosts.html.twig', $data);
        }
    }

    /**
     * @Route("/{judgehostid}", methods={"GET"}, name="jury_judgehost")
     * @throws NonUniqueResultException
     */
    public function viewAction(Request $request, int $judgehostid): Response
    {
        /** @var Judgehost $judgehost */
        $judgehost = $this->em->createQueryBuilder()
            ->from(Judgehost::class, 'j')
            ->select('j')
            ->andWhere('j.judgehostid = :judgehostid')
            ->setParameter('judgehostid', $judgehostid)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$judgehost) {
            throw new NotFoundHttpException(sprintf('Judgehost with ID %d not found', $judgehostid));
        }

        $reltime = floor(Utils::difftime(Utils::now(), (float)$judgehost->getPolltime()));
        if ($reltime < $this->config->get('judgehost_warning')) {
            $statusIcon = 'ok';
            $status = 'OK';
        } elseif ($reltime < $this->config->get('judgehost_critical')) {
            $statusIcon = 'warn';
            $status = 'Warning';
        } else {
            $statusIcon = 'crit';
            $status = 'Critical';
        }

        /** @var Judging[] $judgings */
        $judgings = [];
        if ($contests = $this->dj->getCurrentContest()) {
            $judgings = $this->em->createQueryBuilder()
                ->from(Judging::class, 'j')
                ->select('j', 'r', 'jr', 'jt')
                ->distinct()
                ->innerJoin('j.runs', 'jr')
                ->innerJoin('jr.judgetask', 'jt')
                ->leftJoin('j.rejudging', 'r')
                ->andWhere('j.contest IN (:contests)')
                ->andWhere('jt.judgehost = :judgehost')
                ->setParameter('contests', $contests)
                ->setParameter('judgehost', $judgehost)
                ->orderBy('j.starttime', 'DESC')
                ->addOrderBy('j.judgingid', 'DESC')
                ->getQuery()
                ->getResult();
        }

        $data = [
            'judgehost' => $judgehost,
            'status' => $status,
            'statusIcon' => $statusIcon,
            'judgings' => $judgings,
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_judgehost', ['judgehostid' => $judgehost->getJudgehostid()]),
                'ajax' => true,
            ],
        ];
        if ($request->isXmlHttpRequest()) {
            return $this->render('jury/partials/judgehost_judgings.html.twig', $data);
        } else {
            return $this->render('jury/judgehost.html.twig', $data);
        }
    }

    /**
     * @Route("/{judgehostid}/delete", name="jury_judgehost_delete")
     * @IsGranted("ROLE_ADMIN")
     * @throws DBALException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function deleteAction(Request $request, int $judgehostid): Response
    {
        /** @var Judgehost $judgehost */
        $judgehost = $this->em->createQueryBuilder()
            ->from(Judgehost::class, 'j')
            ->select('j')
            ->andWhere('j.judgehostid = :judgehostid')
            ->setParameter('judgehostid', $judgehostid)
            ->getQuery()
            ->getOneOrNullResult();

        return $this->deleteEntities($request, $this->em, $this->dj, $this->eventLog, $this->kernel,
                                     [$judgehost], $this->generateUrl('jury_judgehosts'));
    }

    /**
     * @Route("/{judgehostid}/enable", name="jury_judgehost_enable")
     * @IsGranted("ROLE_ADMIN")
     */
    public function enableAction(RouterInterface $router, Request $request, int $judgehostid): RedirectResponse
    {
        /** @var Judgehost $judgehost */
        $judgehost = $this->em->getRepository(Judgehost::class)->find($judgehostid);
        $judgehost->setEnabled(true);
        $this->em->flush();
        $this->dj->auditlog('judgehost', $judgehost->getJudgehostid(), 'marked enabled');
        return $this->redirectToLocalReferrer($router, $request, $this->generateUrl('jury_judgehosts'));
    }

    /**
     * @Route("/{judgehostid}/disable", name="jury_judgehost_disable")
     * @IsGranted("ROLE_ADMIN")
     */
    public function disableAction(RouterInterface $router, Request $request, int $judgehostid): RedirectResponse
    {
        /** @var Judgehost $judgehost */
        $judgehost = $this->em->getRepository(Judgehost::class)->find($judgehostid);
        $judgehost->setEnabled(false);
        $this->em->flush();
        $this->dj->auditlog('judgehost', $judgehost->getJudgehostid(), 'marked disabled');
        return $this->redirectToLocalReferrer($router, $request, $this->generateUrl('jury_judgehosts'));
    }

    /**
     * @Route("/enable-all", methods={"POST"}, name="jury_judgehost_enable_all")
     * @IsGranted("ROLE_ADMIN")
     */
    public function enableAllAction(): RedirectResponse
    {
        $this->em->createQuery('UPDATE App\Entity\Judgehost j set j.enabled = true')->execute();
        $this->dj->auditlog('judgehost', null, 'marked all enabled');
        return $this->redirectToRoute('jury_judgehosts');
    }

    /**
     * @Route("/disable-all", methods={"POST"}, name="jury_judgehost_disable_all")
     * @IsGranted("ROLE_ADMIN")
     */
    public function disableAllAction(): RedirectResponse
    {
        $this->em->createQuery('UPDATE App\Entity\Judgehost j set j.enabled = false')->execute();
        $this->dj->auditlog('judgehost', null, 'marked all disabled');
        return $this->redirectToRoute('jury_judgehosts');
    }

    /**
     * @Route("/autohide", methods={"POST"}, name="jury_judgehost_autohide")
     * @IsGranted("ROLE_ADMIN")
     */
    public function autohideInactive(): RedirectResponse
    {
        $now = Utils::now();
        $time_crit = $this->config->get('judgehost_critical');
        $critical_threshold = $now - $time_crit;

        $ret = $this->em->createQuery(
            'UPDATE App\Entity\Judgehost j set j.enabled = false, j.hidden = true WHERE j.polltime IS NULL OR j.polltime < :threshold')
            ->setParameter('threshold', $critical_threshold)
            ->execute();
        $this->dj->auditlog('judgehost', null, 'auto-hiding judgehosts');
        return $this->redirectToRoute('jury_judgehosts');
    }

    /**
     * @Route("/edit/multiple", name="jury_judgehost_edit")
     * @IsGranted("ROLE_ADMIN")
     */
    public function editMultipleAction(Request $request): Response
    {
        $querybuilder = $this->em->createQueryBuilder()
            ->from(Judgehost::class, 'j')
            ->select('j')
            ->orderBy('j.hostname');
        $includeHidden = $request->get('include_hidden', true);
        if (!$includeHidden) {
            $querybuilder->andWhere('j.hidden = 0');
        }
        $judgehosts   = ['judgehosts' => $querybuilder->getQuery()->getResult()];
        $form         = $this->createForm(JudgehostsType::class, $judgehosts);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->dj->auditlog('judgehosts', null, 'updated');
            $this->em->flush();

            return $this->redirectToRoute('jury_judgehosts');
        }

        return $this->render('jury/judgehosts_edit_multiple.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
