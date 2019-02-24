<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Team;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Form\Type\SubmitProblemType;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\SubmissionService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Language;

/**
 * Class SubmissionController
 *
 * @Route("/team")
 * @Security("is_granted('ROLE_TEAM')")
 * @Security("user.getTeam() !== null", message="You do not have a team associated with your account.")
 * @package DOMJudgeBundle\Controller\Team
 */
class SubmissionController extends BaseController
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var SubmissionService
     */
    protected $submissionService;

    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    public function __construct(
        EntityManagerInterface $entityManager,
        SubmissionService $submissionService,
        DOMJudgeService $DOMJudgeService
    ) {
        $this->entityManager     = $entityManager;
        $this->submissionService = $submissionService;
        $this->DOMJudgeService   = $DOMJudgeService;
    }

    /**
     * @Route("/submit", name="team_submit")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function createAction(Request $request)
    {
        $user    = $this->DOMJudgeService->getUser();
        $team    = $user->getTeam();
        $contest = $this->DOMJudgeService->getCurrentContest($user->getTeamid());
        $form    = $this->createForm(SubmitProblemType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($contest === null) {
                $this->addFlash('submissionFail', 'No active contest');
            } elseif (!$this->DOMJudgeService->checkrole('jury') && !$contest->getFreezeData()->started()) {
                $this->addFlash('submissionFail', 'Contest has not yet started');
            } else {
                /** @var Problem $problem */
                $problem = $form->get('problem')->getData();
                /** @var Language $language */
                $language = $form->get('language')->getData();
                /** @var UploadedFile[] $files */
                $files      = $form->get('code')->getData();
                $entryPoint = $form->get('entry_point')->getData() ?: null;
                $submission = $this->submissionService->submitSolution($team, $problem->getProbid(), $contest,
                                                                       $language, $files, null, $entryPoint);

                $this->DOMJudgeService->auditlog('submission', $submission->getSubmitid(), 'added', 'via teampage',
                                                 null, $contest->getCid());
                $this->addFlash('submissionSuccess',
                                '<strong>Submission done!</strong> Watch for the verdict in the list below.');
                return $this->redirectToRoute('legacy.team_index');
            }
        }

        /** @var Language[] $languages */
        $languages = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Language', 'l')
            ->select('l')
            ->andWhere('l.allowSubmit = 1')
            ->getQuery()
            ->getResult();

        return $this->render('@DOMJudge/team/submit.html.twig', [
            'form' => $form->createView(),
            'languages' => $languages,
        ]);
    }
}
