<?php declare(strict_types=1);

namespace App\Controller;

use App\Form\Type\ChangePasswordType;
use App\Service\AuthorizedUserService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\PasswordChangeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
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
        private readonly PasswordChangeService $passwordChangeService,
        private readonly AuthorizedUserService $authService,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    #[Route(path: '/password', name: 'profile_change_password')]
    public function changePasswordAction(Request $request): Response
    {
        // Users can have roles without access to the jury or team interface, e.g. API-only users.
        if ($this->isGranted('ROLE_JURY') || $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_BALLOON')
            || $this->isGranted('ROLE_CLARIFICATION_RW')) {
            $layout = 'jury';
            $redirectRoute = 'jury_index';
        } elseif ($this->isGranted('ROLE_TEAM')) {
            $layout = 'team';
            $redirectRoute = 'team_index';
        } else {
            $layout = 'public';
            $redirectRoute = 'public_index';
        }

        $user = $this->authService->getUser();
        if (!$user || !$this->passwordChangeService->canChangePassword($user)) {
            throw new AccessDeniedHttpException('You are not allowed to change your password.');
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = (string)$form->get('currentPassword')->getData();
            $newPassword = (string)$form->get('newPassword')->getData();

            try {
                $this->passwordChangeService->changePassword($user, $currentPassword, $newPassword);
                $this->addFlash('success', 'Password changed successfully.');
                return $this->redirectToRoute($redirectRoute);
            } catch (BadRequestHttpException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('profile/change_password.html.twig', [
            'form' => $form,
            'redirect_route' => $redirectRoute,
            'layout' => $layout,
        ]);
    }
}
