<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\ExternalSourceWarning;
use App\Form\Type\ExternalSourceWarningsFilterType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ExternalContestSourceService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route(path: '/jury/external-contest')]
class ExternalContestController extends BaseController
{
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        EventLogService $eventLog,
        KernelInterface $kernel,
        private readonly ExternalContestSourceService $sourceService,
    ) {
        parent::__construct($em, $eventLog, $dj, $kernel);
    }

    #[Route(path: '/', name: 'jury_external_contest')]
    public function indexAction(Request $request): Response
    {
        $contest = $this->dj->getCurrentContest();

        if (!$contest) {
            $this->addFlash('warning', 'No active contest. Please select or create a contest first.');
            return $this->redirectToRoute('jury_contests');
        }

        if (!$contest->isExternalSourceEnabled()) {
            $this->addFlash('warning', 'Shadow mode is not enabled for this contest. Configure it in contest settings.');
            return $this->redirect($this->generateUrl('jury_contest_edit', ['contestId' => $contest->getCid()]) . '#externalSourceEnabled');
        }

        $reltime = floor(Utils::difftime(Utils::now(), (float)$contest->getExternalSourceLastPollTime()));
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

        foreach ($contest->getExternalSourceWarnings() as $warning) {
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

        $this->sourceService->setSourceContest($contest);

        // Load preselected filters
        $filters = Utils::jsonDecode((string)$this->dj->getCookie('domjudge_external_source_filter') ?: '[]');

        // Build the filter form.
        $form = $this->createForm(ExternalSourceWarningsFilterType::class, $filters);

        $data = [
            'contest' => $contest,
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
}
