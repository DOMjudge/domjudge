<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class BaseController
 *
 * Base controller other controllers can inherit from to get shared functionality
 *
 * @package DOMJudgeBundle\Controller
 */
abstract class BaseController extends Controller
{
    /**
     * Check whether the referrer in the request is of the current application
     * @param RouterInterface $router
     * @param Request         $request
     * @return bool
     */
    protected function isLocalReferrer(RouterInterface $router, Request $request)
    {
        if ($referrer = $request->headers->get('referer')) {
            $prefix = sprintf('%s%s', $request->getSchemeAndHttpHost(), $request->getBasePath());
            if (strpos($referrer, $prefix) === 0) {
                $path = substr($referrer, strlen($prefix));
                try {
                    $router->match($path);
                    return true;
                } catch (ResourceNotFoundException $e) {
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * Redirect to the referrer if it is a known (local) route, otherwise redirect to the given URL
     * @param RouterInterface $router
     * @param Request         $request
     * @param string          $defaultUrl
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function redirectToLocalReferrer(RouterInterface $router, Request $request, string $defaultUrl)
    {
        if ($this->isLocalReferrer($router, $request)) {
            return $this->redirect($request->headers->get('referer'));
        }

        return $this->redirect($defaultUrl);
    }
}
