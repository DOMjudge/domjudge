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
        if (!$event->getRequest()->attributes->get('check_cid_query')) {
            return;
        }

        $cid = (int)$event->getRequest()->query->get('cid');
        $currentContest = $this->dj->getCurrentContest();

        if (!$cid) {
            if (!$currentContest) {
                return;
            }

            $requestUri = new Uri($event->getRequest()->getRequestUri());

            $queryParameters = ['cid' => $currentContest->getCid()];
            if (!empty($requestUri->getQuery())) {
                parse_str($requestUri->getQuery(), $existingQueryParameters);
                $queryParameters = array_merge($existingQueryParameters, $queryParameters);
            }
            $responseUri = $requestUri->withQuery(http_build_query($queryParameters));

            $event->setResponse(new RedirectResponse((string)$responseUri));
            return;
        }

        if (!$currentContest || $cid != $currentContest->getCid()) {
            if (!$this->dj->getContest($cid)) {
                return;
            }

            $response = new RedirectResponse($event->getRequest()->getRequestUri());
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
