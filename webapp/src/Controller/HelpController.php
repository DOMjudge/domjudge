<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\DOMJudgeService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extra\Markdown\MarkdownRuntime;

#[Route(path: '/public/help')]
class HelpController extends BaseController
{
    #[Route(path: '', name: 'public_help')]
    public function helpAction(): Response
    {
        return $this->render('public/help.html.twig');
    }
}
