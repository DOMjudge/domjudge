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
    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    public function __construct(DOMJudgeService $dj, EntityManagerInterface $em, RequestStack $requestStack)
    {
        $this->dj = $dj;
        $this->em = $em;
        $this->requestStack = $requestStack;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [AuthenticationSuccessEvent::class => 'updateUserState'];
    }

    public function updateUserState(AuthenticationSuccessEvent $event)
    {
        if ($event->getAuthenticationToken() && ($user = $event->getAuthenticationToken()->getUser()) && $user instanceof User) {
            $user->setLastLogin(Utils::now());
            $user->setLastIpAddress($this->dj->getClientIp());

            if (!$user->getFirstLogin()) {
                $user->setFirstLogin(Utils::now());
            }

            $this->em->flush();

            // Only log IP address on the main firewall.
            // Otherwise we also log every API call and we do not want that.
            if (method_exists($event->getAuthenticationToken(), 'getProviderKey') && $event->getAuthenticationToken()->getProviderKey() === 'main') {
                $ip = $this->requestStack->getMasterRequest()->getClientIp();
                $this->dj->auditlog('user', $user->getUserid(), 'logged on on ' . $ip, null, $user->getUserName());
            }
        }
    }
}
