<?php declare(strict_types=1);

namespace DOMJudgeBundle\Security;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Utils\Utils;
use Symfony\Component\Security\Core\Event\AuthenticationEvent;

class UserStateUpdater
{
    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    public function __construct(DOMJudgeService $dj, EntityManagerInterface $entityManager)
    {
        $this->dj            = $dj;
        $this->entityManager = $entityManager;
    }

    public function updateUserState(AuthenticationEvent $event)
    {
        if ($event->getAuthenticationToken() && ($user = $event->getAuthenticationToken()->getUser()) && $user instanceof User) {
            $user->setLastLogin(Utils::now());
            $user->setLastIpAddress($this->dj->getClientIp());

            if ($user->getTeam() && !$user->getTeam()->getTeampageFirstVisited()) {
                $user->getTeam()->setTeampageFirstVisited(Utils::now());
            }

            $this->entityManager->flush();
        }
    }
}
