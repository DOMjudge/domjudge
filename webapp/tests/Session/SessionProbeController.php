<?php declare(strict_types=1);

namespace App\Tests\Session;

use App\Attribute\ReleaseSessionLock;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Actions with known session behaviour, to check that ReleaseSessionLockTest can tell
 * a clean action from one that reopens the session.
 */
#[Route(path: '/test/session-probe')]
final class SessionProbeController extends AbstractController
{
    #[Route(path: '/clean', name: 'test_session_probe_clean')]
    #[ReleaseSessionLock]
    public function clean(): Response
    {
        return new Response('clean');
    }

    #[Route(path: '/reopen', name: 'test_session_probe_reopen')]
    #[ReleaseSessionLock]
    public function reopen(Request $request): Response
    {
        $request->getSession()->get('anything');
        return new Response('reopened');
    }

    #[Route(path: '/unmarked', name: 'test_session_probe_unmarked')]
    public function unmarked(): Response
    {
        return new Response('unmarked');
    }
}
