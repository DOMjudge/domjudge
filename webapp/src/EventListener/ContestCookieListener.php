<?php declare(strict_types=1);

namespace App\EventListener;

use App\Service\DOMJudgeService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Syncs the contest cookie with the contest ID from the URL.
 *
 * When visiting a contest-scoped URL (e.g., /jury/contests/{contestId}/...),
 * this listener updates the contest cookie to match, ensuring the dropdown
 * in the UI shows the same contest as the current page.
 */
readonly class ContestCookieListener
{
    public function __construct(
        protected DOMJudgeService $dj
    ) {}

    #[AsEventListener]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/jury/')) {
            return;
        }

        // Skip change-contest routes - they handle cookie setting in the controller
        $route = $request->attributes->get('_route');
        if ($route === 'jury_change_contest') {
            return;
        }

        $contestId = $request->attributes->get('contestId');

        if ($contestId === null) {
            return;
        }

        $currentCookie = $request->cookies->get('domjudge_cid');

        if ($currentCookie === $contestId) {
            return;
        }

        $response = $event->getResponse();
        $path = $request->getBasePath() ?: '/';
        $response->headers->setCookie(
            Cookie::create('domjudge_cid', $contestId, 0, $path, '', false, false, false, null)
        );
    }
}
