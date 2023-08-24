<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Controller\PublicController;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriNormalizer;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use function GuzzleHttp\Psr7\str;

class ContestIdSubscriber implements EventSubscriberInterface
{
    private DOMJudgeService $dj;
    private array $contestIdUrlActions;

    public function __construct(DOMJudgeService $dj, array $contestIdUrlActions)
    {
        $this->dj = $dj;
        $this->contestIdUrlActions = $contestIdUrlActions;
    }

    public function onKernelController(ControllerEvent $event)
    {
        if (!is_array($event->getController())) {
            return;
        }

        $controllerAction = $event->getController()[1];
        if (!in_array($controllerAction, $this->contestIdUrlActions)) {
            return;
        }

        $event->getRequest()->attributes->set('check_cid_query', true);
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        $request = $event->getRequest();
        $requestUri = new Uri($request->getRequestUri());

        if (!$request->attributes->get('check_cid_query')) {
            return;
        }

        $queryParameters = [];
        if (!empty($requestUri->getQuery())) {
            parse_str($requestUri->getQuery(), $existingQueryParameters);
            $queryParameters = array_merge($existingQueryParameters, $queryParameters);
        }

        $cid = (int)$request->query->get('cid');

        $teamId = $this->dj->getUser() ? $this->dj->getUser()->getTeamId() : -1;
        $currentContest = $this->dj->getCurrentContest($teamId);

        if (!$currentContest) {
            return;
        }

        if (!$cid) {
            $queryParameters['cid'] = $currentContest->getCid();
            $responseUri = $requestUri->withQuery(http_build_query($queryParameters));

            $event->setResponse(new RedirectResponse((string)$responseUri));
            return;
        }

        if (!$this->dj->getContest($cid) ||
            $currentContest->getCid() != $request->cookies->get('domjudge_cid')) {

            $queryParameters['cid'] = $currentContest->getCid();
            $responseUri = $requestUri->withQuery(http_build_query($queryParameters));

            $response = new RedirectResponse((string)$responseUri);

            $response->headers->setCookie(new Cookie('domjudge_cid', (string)$currentContest->getCid()));

            $event->setResponse($response);
            return;
        }

        if ($cid != $currentContest->getCid()) {
            $response = new RedirectResponse($request->getRequestUri());
            $response->headers->setCookie(new Cookie('domjudge_cid', (string)$cid));

            $event->setResponse($response);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }
}
