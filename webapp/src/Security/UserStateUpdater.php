<?php declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Service\DOMJudgeService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;

class UserStateUpdater implements EventSubscriberInterface
{
    protected DOMJudgeService $dj;
    protected EntityManagerInterface $em;
    protected RequestStack $requestStack;

    public function __construct(DOMJudgeService $dj, EntityManagerInterface $em, RequestStack $requestStack)
    {
        $this->dj = $dj;
        $this->em = $em;
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        return [AuthenticationSuccessEvent::class => 'updateUserState'];
    }

    public function updateUserState(AuthenticationSuccessEvent $event): void
    {
        if ($event->getAuthenticationToken() && ($user = $event->getAuthenticationToken()->getUser()) && $user instanceof User) {
            $firewallName = 'main';
            if (method_exists($event->getAuthenticationToken(), 'getFirewallName')) {
                $firewallName = $event->getAuthenticationToken()->getFirewallName();
            }
            if ($firewallName === 'main') {
                $user->setLastLogin(Utils::now());
            } elseif (in_array($firewallName, ['api', 'metrics'], true)) {
                $user->setLastApiLogin(Utils::now());
            }
            $user->setLastIpAddress($this->dj->getClientIp());

            if (!$user->getFirstLogin()) {
                $user->setFirstLogin(Utils::now());
            }

            $this->em->flush();

            // Only log IP address on the main firewall.
            // Otherwise, we would log every API call and we do not want that.
            if ($firewallName === 'main') {
                $ip = $this->requestStack->getMainRequest()->getClientIp();
                $this->dj->auditlog('user', $user->getUserid(), 'logged on on ' . $ip, null, $user->getUserName());
            }
        }
    }
}
