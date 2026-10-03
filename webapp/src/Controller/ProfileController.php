<?php declare(strict_types=1);

namespace App\Controller;

use App\Form\Type\ChangePasswordType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/profile')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ProfileController extends BaseController
{
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        EventLogService $eventLogService,
        KernelInterface $kernel,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    #[Route(path: '', name: 'profile_index')]
    public function changePasswordAction(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $isJury = $this->isGranted('ROLE_JURY') || $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_BALLOON');
        $redirectRoute = $isJury ? 'jury_index' : 'team_index';

        if (!$this->dj->canChangePassword()) {
            throw new AccessDeniedHttpException('You are not allowed to change your password.');
        }

        $user = $this->dj->getUser();
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('newPassword')->getData();

            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $this->saveEntity($user, $user->getUserid(), false);
            $this->addFlash('success', 'Password changed successfully.');
            return $this->redirectToRoute($redirectRoute);
        }

        return $this->render('profile/change_password.html.twig', [
            'form' => $form,
            'redirect_route' => $redirectRoute,
        ]);
    }
}
