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
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/external-contest")
 * @IsGranted("ROLE_ADMIN")
 */
class ExternalContestController extends BaseController
{
    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected EventLogService $eventLog;
    protected KernelInterface $kernel;
    private ExternalContestSourceService $sourceService;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLog,
        KernelInterface $kernel,
        ExternalContestSourceService $sourceService
    ) {
        $this->em = $em;
        $this->dj = $dj;
        $this->config = $config;
        $this->eventLog = $eventLog;
        $this->kernel = $kernel;
        $this->sourceService = $sourceService;
    }

    /**
     * @Route("/", name="jury_external_contest")
     */
    public function indexAction(Request $request): Response
    {
        /** @var ExternalContestSource $externalContestSource */
        $externalContestSource = $this->em->createQueryBuilder()
            ->from(ExternalContestSource::class, 'ecs')
            ->select('ecs')
            ->andWhere('ecs.contest = :contest')
            ->setParameter('contest', $this->dj->getCurrentContest())
            ->getQuery()->getOneOrNullResult();

        if (!$externalContestSource) {
            $this->addFlash('warning', 'No external contest present yet, please configure one first');
            return $this->redirectToRoute('jury_external_contest_manage');
        }

        $reltime = floor(Utils::difftime(Utils::now(), (float)$externalContestSource->getLastPollTime()));
        $status = $reltime < $this->config->get('external_contest_source_critical') ? 'OK' : 'Critical';

        $warningTableFields = [
            'extwarningid' => ['title' => 'ID'],
            'entity_type' => ['title' => 'entity type'],
            'entity_id' => ['title' => 'entity ID'],
            'last_event' => ['title' => 'last event'],
            'type' => ['title' => 'type'],
            'warning_content' => ['title' => 'content'],
        ];

        $warningTable = [];
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        foreach ($externalContestSource->getExternalSourceWarnings() as $warning) {
            $warningData = [];
            foreach ($warningTableFields as $k => $v) {
                if ($propertyAccessor->isReadable($warning, $k)) {
                    $warningData[$k] = ['value' => $propertyAccessor->getValue($warning, $k)];
                }
            }

            $warningData['entity_type']['filterKey'] = 'entity-type';
            $warningData['entity_type']['filterValue'] = $warning->getEntityType();

            $warningData['type']['filterKey'] = 'type';
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
                'data' => $warningData,
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
            'status' => $status,
            'sourceService' => $this->sourceService,
            'webappDir' => $this->dj->getDomjudgeWebappDir(),
            'warningTableFields' => $warningTableFields,
            'warningTable' => $warningTable,
            'form' => $form->createView(),
            'hasFilters' => !empty($filters),
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_external_contest'),
                'ajax' => true,
            ]
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('jury/partials/external_contest_warnings.html.twig', $data);
        } else {
            return $this->render('jury/external_contest.html.twig', $data);
        }
    }

    /**
     * @Route("/manage", name="jury_external_contest_manage")
     */
    public function manageAction(Request $request): Response
    {
        /** @var ExternalContestSource $externalContestSource */
        $externalContestSource = $this->em->createQueryBuilder()
            ->from(ExternalContestSource::class, 'ecs')
            ->select('ecs')
            ->andWhere('ecs.contest = :contest')
            ->setParameter('contest', $this->dj->getCurrentContest())
            ->getQuery()->getOneOrNullResult() ?? new ExternalContestSource();

        if (!$this->dj->getCurrentContest()) {
            if (empty($this->dj->getCurrentContests(null, true))) {
                $this->addFlash('warning', 'No current contest selected, please create one first.');
                return $this->redirect($this->generateUrl('jury_contest_add'));
            } else {
                $this->addFlash('warning', 'No current contest selected, please select one first.');
            }
        } else {
            $externalContestSource->setContest($this->dj->getCurrentContest());
        }

        $form = $this->createForm(ExternalContestSourceType::class, $externalContestSource);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($externalContestSource);
            $this->saveEntity($this->em, $this->eventLog, $this->dj, $externalContestSource, null, true);
            return $this->redirect($this->generateUrl('jury_external_contest'));
        }

        return $this->render('jury/external_contest_manage.html.twig', [
            'externalContestSource' => $externalContestSource,
            'form' => $form->createView(),
        ]);
    }
}
