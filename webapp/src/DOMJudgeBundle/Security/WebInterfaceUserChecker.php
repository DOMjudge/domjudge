<?php declare(strict_types=1);

namespace DOMJudgeBundle\Security;

use DOMJudgeBundle\Entity\User as DOMJudgeUser;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class WebInterfaceUserChecker
 * @package DOMJudgeBundle\Security
 */
class WebInterfaceUserChecker extends UserChecker
{
    /**
     * @param UserInterface $user
     */
    public function checkPreAuth(UserInterface $user)
    {
        if (!$user instanceof DOMJudgeUser) {
            return;
        }

        $disabledWebUsers = ['api-admin', 'kattis', 'cds'];
        if (in_array($user->getUsername(), $disabledWebUsers)) {
            throw new DisabledException();
        }
    }
}
