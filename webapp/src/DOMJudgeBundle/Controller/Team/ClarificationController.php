<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Team;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Entity\Clarification;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Form\Type\TeamClarificationType;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ClarificationController
 *
 * @Route("/team")
 * @Security("is_granted('ROLE_TEAM')")
 * @Security("user.getTeam() !== null", message="You do not have a team associated with your account. ")
 *
 * @package DOMJudgeBundle\Controller\Team
 */
class ClarificationController extends BaseController
{
    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    public function __construct(
        DOMJudgeService $DOMJudgeService,
        EntityManagerInterface $entityManager,
        EventLogService $eventLogService,
        FormFactoryInterface $formFactory
    ) {
        $this->DOMJudgeService = $DOMJudgeService;
        $this->entityManager   = $entityManager;
        $this->eventLogService = $eventLogService;
        $this->formFactory     = $formFactory;
    }

    /**
     * @Route("/clarifications/{clarId}", name="team_clarification", requirements={"clarId": "\d+"})
     * @param Request $request
     * @param int     $clarId
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function viewAction(Request $request, int $clarId)
    {
        $categories = $this->DOMJudgeService->dbconfig_get('clar_categories');
        $user       = $this->DOMJudgeService->getUser();
        $team       = $user->getTeam();
        $contest    = $this->DOMJudgeService->getCurrentContest($team->getTeamid());
        /** @var Clarification|null $clarification */
        $clarification = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Clarification', 'c')
            ->leftJoin('c.problem', 'p')
            ->leftJoin('p.contest_problems', 'cp', Join::WITH, 'cp.contest = :contest')
            ->select('c')
            ->andWhere('c.contest = :contest')
            ->andWhere('c.clarid = :clarId')
            ->setParameter(':contest', $contest)
            ->setParameter(':clarId', $clarId)
            ->getQuery()
            ->getOneOrNullResult();

        $formData = [];
        if ($clarification) {
            if ($clarification->getProbid()) {
                $formData['subject'] = sprintf('%d-%d', $clarification->getCid(), $clarification->getProbid());
            } else {
                $formData['subject'] = sprintf('%d-%s', $clarification->getCid(), $clarification->getQueue());
            }

            $message = '';
            $text    = explode("\n", Utils::wrap_unquoted($clarification->getBody()), 75);
            foreach ($text as $line) {
                $message .= "> $line\n";
            }

            $formData['message'] = $message;
        }
        $form = $this->formFactory
            ->createBuilder(TeamClarificationType::class, $formData)
            ->setAction($this->generateUrl('team_clarification', ['clarId' => $clarId]))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            // First part will always be the contest ID, as Symfony will validate this
            list(, $problemId) = explode('-', $formData['subject']);
            $problem  = null;
            $category = null;
            $queue    = null;
            if (!ctype_digit($problemId)) {
                $category = $problemId;
            } else {
                $problem = $this->entityManager->getRepository(Problem::class)->find($problemId);
                $queue   = $this->DOMJudgeService->dbconfig_get('clar_default_problem_queue');
                if ($queue === "") {
                    $queue = null;
                }
            }

            $newClarification = new Clarification();
            $newClarification
                ->setContest($contest)
                ->setSubmittime(Utils::now())
                ->setSender($team)
                ->setProblem($problem)
                ->setCategory($category)
                ->setQueue($queue)
                ->setBody($formData['message']);

            $this->entityManager->persist($newClarification);
            $this->entityManager->flush();

            $this->DOMJudgeService->auditlog('clarification', $newClarification->getClarid(), 'added', null, null,
                                             $contest->getCid());
            $this->eventLogService->log('clarification', $newClarification->getClarid(), 'create', $contest->getCid());

            $this->addFlash('success', 'Clarification sent to the jury');
            return $this->redirectToRoute('team_index');
        }

        if ($clarification === null) {
            throw new NotFoundHttpException(sprintf('Clarification %d not found', $clarId));
        }

        if (!$team->canViewClarification($clarification)) {
            throw new HttpException(401, 'Permission denied');
        }

        // Get the "parent" message if we have one
        if ($clarification->getInReplyTo()) {
            $clarification = $clarification->getInReplyTo();
        }

        // Mark clarification as read
        $team->removeUnreadClarification($clarification);
        foreach ($clarification->getReplies() as $reply) {
            $team->removeUnreadClarification($reply);
        }
        $this->entityManager->flush();

        $data = [
            'clarification' => $clarification,
            'team' => $team,
            'categories' => $categories,
            'form' => $form->createView(),
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('@DOMJudge/team/clarification_modal.html.twig', $data);
        } else {
            return $this->render('@DOMJudge/team/clarification.html.twig', $data);
        }
    }

    /**
     * @Route("/clarifications/add", name="team_clarification_add")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function addAction(Request $request)
    {
        $categories = $this->DOMJudgeService->dbconfig_get('clar_categories');
        $user       = $this->DOMJudgeService->getUser();
        $team       = $user->getTeam();
        $contest    = $this->DOMJudgeService->getCurrentContest($team->getTeamid());

        $formData = [];
        $form     = $this->formFactory
            ->createBuilder(TeamClarificationType::class, $formData)
            ->setAction($this->generateUrl('team_clarification_add'))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            // First part will always be the contest ID, as Symfony will validate this
            list(, $problemId) = explode('-', $formData['subject']);
            $problem  = null;
            $category = null;
            $queue    = null;
            if (!ctype_digit($problemId)) {
                $category = $problemId;
            } else {
                $problem = $this->entityManager->getRepository(Problem::class)->find($problemId);
                $queue   = $this->DOMJudgeService->dbconfig_get('clar_default_problem_queue');
                if ($queue === "") {
                    $queue = null;
                }
            }

            $newClarification = new Clarification();
            $newClarification
                ->setContest($contest)
                ->setSubmittime(Utils::now())
                ->setSender($team)
                ->setProblem($problem)
                ->setCategory($category)
                ->setQueue($queue)
                ->setBody($formData['message']);

            $this->entityManager->persist($newClarification);
            $this->entityManager->flush();

            $this->DOMJudgeService->auditlog('clarification', $newClarification->getClarid(), 'added', null, null,
                                             $contest->getCid());
            $this->eventLogService->log('clarification', $newClarification->getClarid(), 'create', $contest->getCid());

            $this->addFlash('success', 'Clarification sent to the jury');
            return $this->redirectToRoute('team_index');
        }

        $data = [
            'categories' => $categories,
            'form' => $form->createView(),
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('@DOMJudge/team/clarification_add_modal.html.twig', $data);
        } else {
            return $this->render('@DOMJudge/team/clarification_add.html.twig', $data);
        }
    }
}
