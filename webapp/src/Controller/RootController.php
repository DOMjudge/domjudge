<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\DOMJudgeService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extra\Markdown\MarkdownRuntime;

#[Route(path: '')]
class RootController extends BaseController
{
    public function __construct(protected readonly DOMJudgeService $dj)
    {
    }

    #[Route(path: '', name: 'root')]
    #[Route(path: '', name: 'public_index')]
    public function redirectAction(AuthorizationCheckerInterface $authorizationChecker): Response
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
        return $this->forward(PublicController::class . '::homepageAction');
    }

    #[Route(path: '/markdown-preview', name: 'markdown_preview', methods: ['POST'])]
    public function markdownPreview(
        Request $request,
        #[Autowire(service: 'twig.runtime.markdown')]
        MarkdownRuntime $markdownRuntime,
        HtmlSanitizerInterface $appClarificationSanitizer,
    ): JsonResponse {
        $message = $request->request->get('message');
        if ($message === null) {
            throw new BadRequestHttpException('A message is required');
        }
        return new JsonResponse(['html' => $appClarificationSanitizer->sanitize($markdownRuntime->convert($message))]);
    }
}
