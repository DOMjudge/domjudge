<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Entity\Clarification;
use App\Entity\Contest;
use App\Entity\Problem;
use App\Entity\Team;
use App\Form\Type\TeamClarificationType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ClarificationController
 *
 * @Route("/team")
 * @IsGranted("ROLE_TEAM")
 * @Security("user.getTeam() !== null", message="You do not have a team associated with your account. ")
 *
 * @package App\Controller\Team
 */
class ClarificationController extends BaseController
{
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected EntityManagerInterface $em;
    protected EventLogService $eventLogService;
    protected FormFactoryInterface $formFactory;

    public function __construct(
        DOMJudgeService $dj,
        ConfigurationService $config,
        EntityManagerInterface $em,
        EventLogService $eventLogService,
        FormFactoryInterface $formFactory
    ) {
        $this->dj              = $dj;
        $this->config          = $config;
        $this->em              = $em;
        $this->eventLogService = $eventLogService;
        $this->formFactory     = $formFactory;
    }

    /**
     * @Route("/clarifications/{clarId<\d+>}", name="team_clarification")
     * @throws NonUniqueResultException
     */
    public function viewAction(Request $request, int $clarId): Response
    {
        $categories = $this->config->get('clar_categories');
        $user       = $this->dj->getUser();
        $team       = $user->getTeam();
        $contest    = $this->dj->getCurrentContest($team->getTeamid());
        /** @var Clarification|null $clarification */
        $clarification = $this->em->createQueryBuilder()
            ->from(Clarification::class, 'c')
            ->leftJoin('c.problem', 'p')
            ->leftJoin('c.contest', 'co')
            ->leftJoin('p.contest_problems', 'cp', Join::WITH, 'cp.contest = :contest')
            ->select('c, p, co')
            ->andWhere('c.contest = :contest')
            ->andWhere('c.clarid = :clarId')
            ->setParameter('contest', $contest)
            ->setParameter('clarId', $clarId)
            ->getQuery()
            ->getOneOrNullResult();

        $formData = [];
        if ($clarification) {
            if ($clarification->getProblem()) {
                $formData['subject'] = sprintf('%d-%d', $clarification->getContest()->getCid(), $clarification->getProblem()->getProbid());
            } else {
                $formData['subject'] = sprintf('%d-%s', $clarification->getContest()->getCid(), $clarification->getQueue());
            }

            $message = '';
            $text    = explode("\n", Utils::wrapUnquoted($clarification->getBody()), 75);
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
            $this->newClarificationHelper($form, $contest, $team);
            return $this->redirectToRoute('team_index');
        }

        if ($clarification === null) {
            throw new NotFoundHttpException(sprintf('Clarification %d not found', $clarId));
        }

        if (!$team->canViewClarification($clarification)) {
            throw new HttpException(401, 'Permission denied');
        }

        // Get the "parent" message if we have one.
        if ($clarification->getInReplyTo()) {
            $clarification = $clarification->getInReplyTo();
        }

        // Mark clarification as read.
        $team->removeUnreadClarification($clarification);
        foreach ($clarification->getReplies() as $reply) {
            $team->removeUnreadClarification($reply);
        }
        $this->em->flush();

        $data = [
            'clarification' => $clarification,
            'team' => $team,
            'categories' => $categories,
            'form' => $form->createView(),
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('team/clarification_modal.html.twig', $data);
        } else {
            return $this->render('team/clarification.html.twig', $data);
        }
    }

    /**
     * @Route("/clarifications/add", name="team_clarification_add")
     */
    public function addAction(Request $request): Response
    {
        $categories = $this->config->get('clar_categories');
        $user       = $this->dj->getUser();
        $team       = $user->getTeam();
        $contest    = $this->dj->getCurrentContest($team->getTeamid());

        $formData = [];
        $form     = $this->formFactory
            ->createBuilder(TeamClarificationType::class, $formData)
            ->setAction($this->generateUrl('team_clarification_add'))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->newClarificationHelper($form, $contest, $team);
            return $this->redirectToRoute('team_index');
        }

        $data = [
            'categories' => $categories,
            'form' => $form->createView(),
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('team/clarification_add_modal.html.twig', $data);
        } else {
            return $this->render('team/clarification_add.html.twig', $data);
        }
    }

    /**
     * @throws NonUniqueResultException
     */
    private function newClarificationHelper(
        FormInterface $form,
        ?Contest $contest,
        Team $team
    ): void {
        $formData = $form->getData();
        // First part will always be the contest ID, as Symfony will validate this.
        [, $problemId] = explode('-', $formData['subject']);
        $problem = null;
        $category = null;
        $queue = null;
        if (!ctype_digit($problemId)) {
            $category = $problemId;
        } else {
            $problem = $this->em->getRepository(Problem::class)->find($problemId);
            $queue = $this->config->get('clar_default_problem_queue');
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

        $this->em->persist($newClarification);
        $this->em->flush();

        $this->dj->auditlog('clarification', $newClarification->getClarid(), 'added', null, null,
            $contest->getCid());
        $this->eventLogService->log('clarification', $newClarification->getClarid(), 'create', $contest->getCid());

        $this->addFlash('success', 'Clarification sent to the jury');
    }
}
