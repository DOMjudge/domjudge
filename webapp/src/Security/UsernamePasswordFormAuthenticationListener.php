<?php declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Firewall\UsernamePasswordFormAuthenticationListener as BaseUsernamePasswordFormAuthenticationListener;

/**
 * Class UsernamePasswordFormAuthenticationListener
 * @package App\Security
 */
class UsernamePasswordFormAuthenticationListener extends BaseUsernamePasswordFormAuthenticationListener
{
    /**
     * @inheritDoc
     */
    protected function requiresAuthentication(Request $request)
    {
        // Disable the check when logging in with a custom authentication method
        if ($request->attributes->get('_route') === 'login'
            && $request->isMethod('POST')
            && $request->request->has('loginmethod')) {
            return false;
        }
        return parent::requiresAuthentication($request);
    }


}
