<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\DOMJudgeService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route(path: '')]
class RootController extends BaseController
{
    public function __construct(protected readonly DOMJudgeService $dj)
    {
    }

    #[Route(path: '', name: 'root')]
    public function redirectAction(AuthorizationCheckerInterface $authorizationChecker): RedirectResponse
    {
        if ($authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            if ($this->dj->checkrole('jury')) {
                return $this->redirectToRoute('jury_index');
            }
            if ($this->dj->checkrole('team', false)) {
                return $this->redirectToRoute('team_index');
            }
            if ($this->dj->checkrole('balloon')) {
                return $this->redirectToRoute('jury_balloons');
            }
            if ($this->dj->checkrole('clarification_rw')) {
                return $this->redirectToRoute('jury_clarifications');
            }
        }
        return $this->redirectToRoute('public_index');
    }
}
