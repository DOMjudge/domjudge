<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\ExternalContestSource;
use App\Entity\ExternalSourceWarning;
use App\Form\Type\ExternalContestSourceType;
use App\Form\Type\ExternalSourceWarningsFilterType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ExternalContestSourceService;
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
 * @Route("/jury/external-contest-sources")
 * @IsGranted("ROLE_ADMIN")
 */
class ExternalContestSourceController extends BaseController
{
    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected EventLogService $eventLog;
    protected KernelInterface $kernel;
    private ExternalContestSourceService $sourceService;

    public function __construct(
        EntityManagerInterface       $em,
        DOMJudgeService              $dj,
        ConfigurationService         $config,
        EventLogService              $eventLog,
        KernelInterface              $kernel,
        ExternalContestSourceService $sourceService
    ) {
        $this->em            = $em;
        $this->dj            = $dj;
        $this->config        = $config;
        $this->eventLog      = $eventLog;
        $this->kernel        = $kernel;
        $this->sourceService = $sourceService;
    }

    /**
     * @Route("/", name="jury_external_contest_sources")
     */
    public function indexAction(Request $request): Response
    {
        /** @var ExternalContestSource[] $externalContestSources */
        $externalContestSources = $this->em->createQueryBuilder()
                                           ->from(ExternalContestSource::class, 'ecs')
                                           ->select('ecs')
                                           ->orderBy('ecs.extsourceid')
                                           ->getQuery()->getResult();

        $tableFields = [
            'extsourceid'   => ['title' => 'ID'],
            'contest'       => ['title' => 'contest'],
            'enabled'       => ['title' => 'enabled'],
            'type'          => ['title' => 'type'],
            'source'        => ['title' => 'source'],
            'status'        => ['title' => 'status'],
            'last_event_id' => ['title' => 'last event ID'],
        ];

        $now = Utils::now();

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $timeCrit         = $this->config->get('external_contest_source_critical');
        $sourcesTable     = [];
        foreach ($externalContestSources as $externalContestSource) {
            $sourceData    = [];
            $sourceActions = [];
            // Get whatever fields we can from the object itself
            foreach ($tableFields as $k => $v) {
                if ($propertyAccessor->isReadable($externalContestSource, $k)) {
                    $sourceData[$k] = ['value' => $propertyAccessor->getValue($externalContestSource, $k)];
                }
            }

            $cid                            = $externalContestSource->getContest()->getCid();
            $sourceData['contest']['value'] = "c$cid";
            $sourceData['contest']['link']  = $this->generateUrl('jury_contest', ['contestId' => $cid]);

            $sourceData['type']['value'] = ExternalContestSource::readableType($externalContestSource->getType());

            if (empty($externalContestSource->getLastPollTime())) {
                $status      = 'noconn';
                $statusTitle = "never checked in";
            } else {
                $relTime = floor(Utils::difftime($now, (float)$externalContestSource->getLastPollTime()));
                if ($relTime < $timeCrit) {
                    $status = 'ok';
                } else {
                    $status = 'crit';
                }
                $statusTitle = sprintf('last checked in %ss ago',
                                       Utils::printtimediff((float)$externalContestSource->getLastPollTime()));
            }

            if ($externalContestSource->getLastEventId() === null) {
                $sourceData['last_event'] = ['value' => '-'];
            }

            $sourceData = array_merge($sourceData, [
                'status'  => [
                    'value' => $status,
                    'title' => $statusTitle,
                ],
                'enabled' => [
                    'value' => $externalContestSource->isEnabled() ? 'yes' : 'no',
                ],
            ]);

            // Create action links
            if ($externalContestSource->isEnabled()) {
                $enableicon = 'pause';
                $enablecmd  = 'disable';
                $route      = 'jury_external_contest_source_disable';
            } else {
                $enableicon = 'play';
                $enablecmd  = 'enable';
                $route      = 'jury_external_contest_source_enable';
            }
            $sourceActions[] = [
                'icon'  => $enableicon,
                'title' => sprintf('%s external contest source', $enablecmd),
                'link'  => $this->generateUrl($route, ['extsourceid' => $externalContestSource->getExtsourceid()]),
            ];

            $sourceActions[] = [
                'icon'  => 'edit',
                'title' => 'edit this external contest source',
                'link'  => $this->generateUrl('jury_external_contest_source_edit', [
                    'extsourceid' => $externalContestSource->getExtsourceid(),
                ]),
            ];

            $sourceActions[] = [
                'icon'      => 'trash-alt',
                'title'     => 'delete this external contest source',
                'link'      => $this->generateUrl('jury_external_contest_source_delete', [
                    'extsourceid' => $externalContestSource->getExtsourceid(),
                ]),
                'ajaxModal' => true,
            ];

            // Save this to our list of rows
            $sourcesTable[] = [
                'data'     => $sourceData,
                'actions'  => $sourceActions,
                'link'     => $this->generateUrl('jury_external_contest_source', [
                    'extsourceid' => $externalContestSource->getExtsourceid()
                ]),
                'cssclass' => $externalContestSource->isEnabled() ? '' : 'disabled',
            ];
        }

        $data = [
            'externalContestSources' => $sourcesTable,
            'tableFields'            => $tableFields,
            'numActions'             => 3,
            'refresh'                => [
                'after' => 5,
                'url'   => $this->generateUrl('jury_external_contest_sources'),
                'ajax'  => true,
            ]
        ];
        if ($request->isXmlHttpRequest()) {
            return $this->render('jury/partials/external_contest_source_list.html.twig', $data);
        } else {
            return $this->render('jury/external_contest_sources.html.twig', $data);
        }
    }

    /**
     * @Route("/{extsourceid<\d+>}", methods={"GET"}, name="jury_external_contest_source")
     * @throws NonUniqueResultException
     */
    public function viewAction(Request $request, int $extsourceid): Response
    {
        /** @var ExternalContestSource $externalContestSource */
        $externalContestSource = $this->em->createQueryBuilder()
                                          ->from(ExternalContestSource::class, 'ecs')
                                          ->leftJoin('ecs.warnings', 'w')
                                          ->select('ecs, w')
                                          ->andWhere('ecs.extsourceid = :extsourceid')
                                          ->setParameter('extsourceid', $extsourceid)
                                          ->getQuery()
                                          ->getOneOrNullResult();

        if (!$externalContestSource) {
            throw new NotFoundHttpException(sprintf('External contest source with ID %d not found', $extsourceid));
        }

        $reltime = floor(Utils::difftime(Utils::now(), (float)$externalContestSource->getLastPollTime()));
        $status  = $reltime < $this->config->get('external_contest_source_critical') ? 'OK' : 'Critical';

        $warningTableFields = [
            'extwarningid'    => ['title' => 'ID'],
            'entity_type'     => ['title' => 'entity type'],
            'entity_id'       => ['title' => 'entity ID'],
            'last_event'      => ['title' => 'last event'],
            'type'            => ['title' => 'type'],
            'warning_content' => ['title' => 'content'],
        ];

        $warningTable     = [];
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        foreach ($externalContestSource->getExternalSourceWarnings() as $warning) {
            $warningData = [];
            foreach ($warningTableFields as $k => $v) {
                if ($propertyAccessor->isReadable($warning, $k)) {
                    $warningData[$k] = ['value' => $propertyAccessor->getValue($warning, $k)];
                }
            }

            $warningData['entity_type']['filterKey']   = 'entity-type';
            $warningData['entity_type']['filterValue'] = $warning->getEntityType();

            $warningData['type']['filterKey']   = 'type';
            $warningData['type']['filterValue'] = $warning->getType();

            $warningData['last_event'] = [
                'value' => sprintf(
                    '%s at %s',
                    $warning->getLastEventId(),
                    Utils::printtime($warning->getLastTime(), 'Y-m-d H:i:s (T)')
                )
            ];

            $warningData['type']['value'] = ExternalSourceWarning::readableType($warningData['type']['value']);

            // Gets parsed and displayed in the Twig Extension
            $warningData['warning_content'] = ['value' => $warning];

            $warningTable[] = [
                'data'    => $warningData,
                'actions' => [],
            ];
        }

        $this->sourceService->setSource($externalContestSource);

        // Load preselected filters
        $filters = $this->dj->jsonDecode((string)$this->dj->getCookie('domjudge_external_source_filter') ?: '[]');

        // Build the filter form.
        $form = $this->createForm(ExternalSourceWarningsFilterType::class, $filters);

        $data = [
            'externalContestSource' => $externalContestSource,
            'status'                => $status,
            'sourceService'         => $this->sourceService,
            'webappDir'             => $this->dj->getDomjudgeWebappDir(),
            'warningTableFields'    => $warningTableFields,
            'warningTable'          => $warningTable,
            'form'                  => $form->createView(),
            'hasFilters'            => !empty($filters),
            'refresh'               => [
                'after' => 15,
                'url'   => $this->generateUrl('jury_external_contest_source', ['extsourceid' => $extsourceid]),
                'ajax'  => true,
            ]
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('jury/partials/external_contest_source_warnings.html.twig', $data);
        } else {
            return $this->render('jury/external_contest_source.html.twig', $data);
        }
    }

    /**
     * @Route("/{extsourceid<\d+>}/edit", name="jury_external_contest_source_edit")
     */
    public function editAction(Request $request, int $extsourceid): Response
    {
        /** @var ExternalContestSource $externalContestSource */
        $externalContestSource = $this->em->getRepository(ExternalContestSource::class)->find($extsourceid);
        if (!$externalContestSource) {
            throw new NotFoundHttpException(sprintf('External contest source with ID %s not found', $extsourceid));
        }

        $form = $this->createForm(ExternalContestSourceType::class, $externalContestSource);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->saveEntity($this->em, $this->eventLog, $this->dj, $externalContestSource,
                              $externalContestSource->getExtsourceid(), false);
            return $this->redirect($this->generateUrl(
                'jury_external_contest_source',
                ['extsourceid' => $externalContestSource->getExtsourceid()]
            ));
        }

        return $this->render('jury/external_contest_source_edit.html.twig', [
            'externalContestSource' => $externalContestSource,
            'form'                  => $form->createView(),
        ]);
    }

    /**
     * @Route("/add", name="jury_external_contest_source_add")
     */
    public function addAction(Request $request): Response
    {
        $externalContestSource = new ExternalContestSource();
        $form                  = $this->createForm(ExternalContestSourceType::class, $externalContestSource);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($externalContestSource);
            $this->saveEntity($this->em, $this->eventLog, $this->dj, $externalContestSource, null, true);
            return $this->redirect($this->generateUrl(
                'jury_external_contest_source',
                ['extsourceid' => $externalContestSource->getExtsourceid()]
            ));
        }

        return $this->render('jury/external_contest_source_add.html.twig', [
            'externalContestSource' => $externalContestSource,
            'form'                  => $form->createView(),
        ]);
    }

    /**
     * @Route("/{extsourceid<\d+>}/delete", name="jury_external_contest_source_delete")
     * @throws DBALException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function deleteAction(Request $request, int $extsourceid): Response
    {
        /** @var ExternalContestSource $externalContestSource */
        $externalContestSource = $this->em->getRepository(ExternalContestSource::class)->find($extsourceid);

        return $this->deleteEntities($request, $this->em, $this->dj, $this->eventLog, $this->kernel,
                                     [$externalContestSource], $this->generateUrl('jury_external_contest_sources'));
    }

    /**
     * @Route("/{extsourceid<\d+>}/enable", name="jury_external_contest_source_enable")
     */
    public function enableAction(RouterInterface $router, Request $request, int $extsourceid): RedirectResponse
    {
        /** @var ExternalContestSource $externalContestSource */
        $externalContestSource = $this->em->getRepository(ExternalContestSource::class)->find($extsourceid);
        $externalContestSource->setEnabled(true);
        $this->em->flush();
        $this->dj->auditlog('judgehost', $externalContestSource->getextsourceid(), 'marked enabled');
        return $this->redirectToLocalReferrer($router, $request, $this->generateUrl('jury_external_contest_sources'));
    }

    /**
     * @Route("/{extsourceid<\d+>}/disable", name="jury_external_contest_source_disable")
     */
    public function disableAction(RouterInterface $router, Request $request, int $extsourceid): RedirectResponse
    {
        /** @var ExternalContestSource $externalContestSource */
        $externalContestSource = $this->em->getRepository(ExternalContestSource::class)->find($extsourceid);
        $externalContestSource->setEnabled(false);
        $this->em->flush();
        $this->dj->auditlog('judgehost', $externalContestSource->getextsourceid(), 'marked disabled');
        return $this->redirectToLocalReferrer($router, $request, $this->generateUrl('jury_external_contest_sources'));
    }
}
