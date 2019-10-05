<?php declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Service\DOMJudgeService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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

    public function __construct(DOMJudgeService $dj, EntityManagerInterface $em)
    {
        $this->dj = $dj;
        $this->em = $em;
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
        }
    }
}
