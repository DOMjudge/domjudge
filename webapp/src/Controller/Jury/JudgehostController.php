<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Judgehost;
use App\Entity\Judging;
use App\Form\Type\JudgehostsType;
use App\Service\DOMJudgeService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
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
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * JudgehostController constructor.
     * @param EntityManagerInterface $em
     * @param DOMJudgeService $dj
     * @param KernelInterface $kernel
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        KernelInterface $kernel
    ) {
        $this->em = $em;
        $this->dj = $dj;
        $this->kernel = $kernel;
    }

    /**
     * @Route("", name="jury_judgehosts")
     */
    public function indexAction(Request $request)
    {
        /** @var Judgehost[] $judgehosts */
        $judgehosts = $this->em->createQueryBuilder()
            ->from(Judgehost::class, 'j')
            ->leftJoin('j.restriction', 'r')
            ->select('j', 'r')
            ->orderBy('j.hostname')
            ->getQuery()->getResult();

        $table_fields = [
            'hostname' => ['title' => 'hostname'],
            'active' => ['title' => 'active'],
            'status' => ['title' => 'status'],
            'restriction' => ['title' => 'restriction'],
            'load' => ['title' => 'load'],
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
        $time_warn = $this->dj->dbconfig_get('judgehost_warning', 30);
        $time_crit = $this->dj->dbconfig_get('judgehost_critical', 120);
        $judgehosts_table = [];
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
            } else {
                $relTime = floor(Utils::difftime($now, (float)$judgehost->getPolltime()));
                if ($relTime < $time_warn) {
                    $status = 'ok';
                } elseif ($relTime < $time_crit) {
                    $status = 'warn';
                } else {
                    $status = 'crit';
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
     * @param Request $request
     * @param string  $hostname
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function viewAction(Request $request, string $hostname)
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
        if ($reltime < $this->dj->dbconfig_get('judgehost_warning', 30)) {
            $status = 'OK';
        } elseif ($reltime < $this->dj->dbconfig_get('judgehost_critical', 120)) {
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
     * @param Request $request
     * @param string  $hostname
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function deleteAction(Request $request, string $hostname)
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

        return $this->deleteEntity($request, $this->em, $this->dj, $this->kernel, $judgehost, $judgehost->getHostname(), $this->generateUrl('jury_judgehosts'));
    }

    /**
     * @Route("/{hostname}/activate", name="jury_judgehost_activate")
     * @IsGranted("ROLE_ADMIN")
     * @param RouterInterface $router
     * @param Request         $request
     * @param string          $hostname
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function activateAction(RouterInterface $router, Request $request, string $hostname)
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
     * @param RouterInterface $router
     * @param Request         $request
     * @param string          $hostname
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deactivateAction(RouterInterface $router, Request $request, string $hostname)
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
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function activateAllAction()
    {
        $this->em->createQuery('UPDATE App\Entity\Judgehost j set j.active = true')->execute();
        $this->dj->auditlog('judgehost', null, 'marked all active');
        return $this->redirectToRoute('jury_judgehosts');
    }

    /**
     * @Route("/deactivate-all", methods={"POST"}, name="jury_judgehost_deactivate_all")
     * @IsGranted("ROLE_ADMIN")
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deactivateAllAction()
    {
        $this->em->createQuery('UPDATE App\Entity\Judgehost j set j.active = false')->execute();
        $this->dj->auditlog('judgehost', null, 'marked all inactive');
        return $this->redirectToRoute('jury_judgehosts');
    }

    /**
     * @Route("/add/multiple", name="jury_judgehost_add")
     * @IsGranted("ROLE_ADMIN")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addMultipleAction(Request $request)
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
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editMultipleAction(Request $request)
    {
        $querybuilder = $this->em->createQueryBuilder()
            ->from(Judgehost::class, 'j')
            ->select('j')
            ->orderBy('j.hostname');
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
