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
use Doctrine\DBAL\DBALException;
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
    // Note: when adding or modifying routes, make sure they do not clash with the /judgehosts/{hostname} route

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * @var EventLogService
     */
    protected $eventLog;

    /**
     * @var KernelInterface
     */
    protected $kernel;

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
            ->leftJoin('j.restriction', 'r')
            ->select('j', 'r')
            ->andWhere('j.hidden = 0')
            ->orderBy('j.hostname')
            ->getQuery()->getResult();

        $table_fields = [
            'hostname' => ['title' => 'hostname'],
            'active' => ['title' => 'active'],
            'status' => ['title' => 'status'],
            'restriction' => ['title' => 'restriction'],
            'load' => ['title' => 'load'],
            'last_judgingid' => ['title' => 'last judging'],
        ];

        $now           = Utils::now();
        $contest       = $this->dj->getCurrentContest();
        $query         = 'SELECT judgehost, SUM(IF(endtime, endtime, :now) - GREATEST(:from, starttime)) AS `load`
                          FROM judging
                          WHERE endtime > :from OR (endtime IS NULL AND (valid = 1 OR rejudgingid IS NOT NULL))
                          GROUP BY judgehost';
        $params        = [':now' => $now];

        $params[':from'] = $now - 2 * 60;
        $work2min        = $this->em->getConnection()->fetchAll($query, $params);
        $params[':from'] = $now - 10 * 60;
        $work10min       = $this->em->getConnection()->fetchAll($query, $params);
        $params[':from'] = $contest ? $contest->getStarttime() : 0;
        $workcontest     = $this->em->getConnection()->fetchAll($query, $params);

        $map = function ($work) {
            $result = [];
            foreach ($work as $item) {
                $result[$item['judgehost']] = $item['load'];
            }

            return $result;
        };

        $work2min    = $map($work2min);
        $work10min   = $map($work10min);
        $workcontest = $map($workcontest);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $time_warn = $this->config->get('judgehost_warning');
        $time_crit = $this->config->get('judgehost_critical');
        $judgehosts_table = [];
        $all_checked_in_recently = true;
        foreach ($judgehosts as $judgehost) {
            $judgehostdata    = [];
            $judgehostactions = [];
            // Get whatever fields we can from the problem object itself
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

            $load = sprintf(
                '%.2f&nbsp;%.2f&nbsp;',
                ($work2min[$judgehost->getHostname()] ?? 0) / (2 * 60),
                ($work10min[$judgehost->getHostname()] ?? 0) / (10 * 60)
            );
            if ( $contest ) {
                $contestLength = Utils::difftime($now, (float)$contest->getStarttime());
                $load .= sprintf(
                    '%.2f',
                    ($workcontest[$judgehost->getHostname()] ?? 0) / $contestLength
                );
            } else {
                $load .= 'N/A';
            }

            $lastJobId = $this->em->createQueryBuilder()
                ->from(JudgeTask::class, 'jt')
                ->select('jt.jobid')
                ->andWhere('jt.hostname = :hostname')
                ->andWhere('jt.type = :type')
                ->setParameter(':hostname', $judgehost->getHostname())
                ->setParameter(':type', JudgeTaskType::JUDGING_RUN)
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
                'load' => [
                    'title' => 'load during the last 2 and 10 minutes and the whole contest',
                    'value' => $load,
                ],
                'active' => [
                    'value' => $judgehost->getActive() ? 'yes' : 'no',
                ],
                'restriction' => [
                    'value' => $judgehost->getRestriction() ? $judgehost->getRestriction()->getName() : '<i>none</i>',
                ],
            ]);

            // Create action links
            if ($this->isGranted('ROLE_ADMIN')) {
                if ($judgehost->getActive()) {
                    $activeicon = 'pause';
                    $activecmd  = 'deactivate';
                    $route      = 'jury_judgehost_deactivate';
                } else {
                    $activeicon = 'play';
                    $activecmd  = 'activate';
                    $route      = 'jury_judgehost_activate';
                }
                $judgehostactions[] = [
                    'icon' => $activeicon,
                    'title' => sprintf('%s judgehost', $activecmd),
                    'link' => $this->generateUrl($route, ['hostname' => $judgehost->getHostname()]),
                ];

                $judgehostactions[] = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this judgehost',
                    'link' => $this->generateUrl('jury_judgehost_delete', [
                        'hostname' => $judgehost->getHostname(),
                    ]),
                    'ajaxModal' => true,
                ];
            }

            // Save this to our list of rows
            $judgehosts_table[] = [
                'data' => $judgehostdata,
                'actions' => $judgehostactions,
                'link' => $this->generateUrl('jury_judgehost', ['hostname' => $judgehost->getHostname()]),
                'cssclass' => $judgehost->getActive() ? '' : 'disabled',
            ];
        }

        $data = [
            'judgehosts' => $judgehosts_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 2 : 0,
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
     * @Route("/{hostname}", methods={"GET"}, name="jury_judgehost")
     * @throws NonUniqueResultException
     */
    public function viewAction(Request $request, string $hostname): Response
    {
        /** @var Judgehost $judgehost */
        $judgehost = $this->em->createQueryBuilder()
            ->from(Judgehost::class, 'j')
            ->leftJoin('j.restriction', 'r')
            ->select('j', 'r')
            ->andWhere('j.hostname = :hostname')
            ->setParameter(':hostname', $hostname)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$judgehost) {
            throw new NotFoundHttpException(sprintf('Judgehost with hostname %s not found', $hostname));
        }

        $reltime = floor(Utils::difftime(Utils::now(), (float)$judgehost->getPolltime()));
        if ($reltime < $this->config->get('judgehost_warning')) {
            $status = 'OK';
        } elseif ($reltime < $this->config->get('judgehost_critical')) {
            $status = 'Warning';
        } else {
            $status = 'Critical';
        }

        /** @var Judging[] $judgings */
        $judgings = [];
        if ($contests = $this->dj->getCurrentContest()) {
            $judgings = $this->em->createQueryBuilder()
                ->from(Judging::class, 'j')
                ->select('j', 'r')
                ->leftJoin('j.rejudging', 'r')
                ->andWhere('j.contest IN (:contests)')
                ->andWhere('j.judgehost = :judgehost')
                ->setParameter(':contests', $contests)
                ->setParameter(':judgehost', $judgehost)
                ->orderBy('j.starttime', 'DESC')
                ->addOrderBy('j.judgingid', 'DESC')
                ->getQuery()
                ->getResult();
        }

        $data = [
            'judgehost' => $judgehost,
            'status' => $status,
            'judgings' => $judgings,
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_judgehost', ['hostname' => $judgehost->getHostname()]),
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
     * @Route("/{hostname}/delete", name="jury_judgehost_delete")
     * @IsGranted("ROLE_ADMIN")
     * @throws DBALException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function deleteAction(Request $request, string $hostname): Response
    {
        /** @var Judgehost $judgehost */
        $judgehost = $this->em->createQueryBuilder()
            ->from(Judgehost::class, 'j')
            ->leftJoin('j.restriction', 'r')
            ->select('j', 'r')
            ->andWhere('j.hostname = :hostname')
            ->setParameter(':hostname', $hostname)
            ->getQuery()
            ->getOneOrNullResult();

        return $this->deleteEntity($request, $this->em, $this->dj, $this->eventLog, $this->kernel,
                                   $judgehost, $judgehost->getHostname(), $this->generateUrl('jury_judgehosts'));
    }

    /**
     * @Route("/{hostname}/activate", name="jury_judgehost_activate")
     * @IsGranted("ROLE_ADMIN")
     */
    public function activateAction(RouterInterface $router, Request $request, string $hostname): RedirectResponse
    {
        $judgehost = $this->em->getRepository(Judgehost::class)->find($hostname);
        $judgehost->setActive(true);
        $this->em->flush();
        $this->dj->auditlog('judgehost', $hostname, 'marked active');
        return $this->redirectToLocalReferrer($router, $request, $this->generateUrl('jury_judgehosts'));
    }

    /**
     * @Route("/{hostname}/deactivate", name="jury_judgehost_deactivate")
     * @IsGranted("ROLE_ADMIN")
     */
    public function deactivateAction(RouterInterface $router, Request $request, string $hostname): RedirectResponse
    {
        $judgehost = $this->em->getRepository(Judgehost::class)->find($hostname);
        $judgehost->setActive(false);
        $this->em->flush();
        $this->dj->auditlog('judgehost', $hostname, 'marked inactive');
        return $this->redirectToLocalReferrer($router, $request, $this->generateUrl('jury_judgehosts'));
    }

    /**
     * @Route("/activate-all", methods={"POST"}, name="jury_judgehost_activate_all")
     * @IsGranted("ROLE_ADMIN")
     */
    public function activateAllAction(): RedirectResponse
    {
        $this->em->createQuery('UPDATE App\Entity\Judgehost j set j.active = true')->execute();
        $this->dj->auditlog('judgehost', null, 'marked all active');
        return $this->redirectToRoute('jury_judgehosts');
    }

    /**
     * @Route("/deactivate-all", methods={"POST"}, name="jury_judgehost_deactivate_all")
     * @IsGranted("ROLE_ADMIN")
     */
    public function deactivateAllAction(): RedirectResponse
    {
        $this->em->createQuery('UPDATE App\Entity\Judgehost j set j.active = false')->execute();
        $this->dj->auditlog('judgehost', null, 'marked all inactive');
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
            'UPDATE App\Entity\Judgehost j set j.active = false, j.hidden = true WHERE j.polltime IS NULL OR j.polltime < :threshold')
            ->setParameter(':threshold', $critical_threshold)
            ->execute();
        dump($ret);
        $this->dj->auditlog('judgehost', null, 'auto-hiding judgehosts');
        return $this->redirectToRoute('jury_judgehosts');
    }

    /**
     * @Route("/add/multiple", name="jury_judgehost_add")
     * @IsGranted("ROLE_ADMIN")
     */
    public function addMultipleAction(Request $request): Response
    {
        $judgehosts = ['judgehosts' => [new Judgehost()]];
        $form       = $this->createForm(JudgehostsType::class, $judgehosts);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Judgehost $judgehost */
            foreach ($form->getData()['judgehosts'] as $judgehost) {
                $this->em->persist($judgehost);
                $this->dj->auditlog('judgehost', $judgehost->getHostname(), 'added');
            }
            $this->em->flush();

            return $this->redirectToRoute('jury_judgehosts');
        }

        return $this->render('jury/judgehosts_add_multiple.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/edit/multiple", name="jury_judgehost_edit")
     * @IsGranted("ROLE_ADMIN")
     */
    public function editMultipleAction(Request $request) : Response
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
