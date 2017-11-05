<?php
namespace DOMJudgeBundle\Security;

use DOMJudgeBundle\Security\Authentication\Token\AlternateLoginToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\SimpleFormAuthenticatorInterface;

class DOMJudgeFormAuthenticator implements SimpleFormAuthenticatorInterface
{
    private $encoder;
    private $container;
    private $em;

    public function __construct(UserPasswordEncoderInterface $encoder, Container $container, EntityManagerInterface $em)
    {
        $this->encoder = $encoder;
        $this->container = $container;
        $this->em = $em;
    }

    public function authenticateToken(TokenInterface $token, UserProviderInterface $userProvider, $providerKey)
    {
        $clientIP = $this->container->get('request_stack')->getMasterRequest()->getClientIp();
        $authmethods = [];
        if ($this->container->hasParameter('domjudge.authmethods')) {
          $authmethods = $this->container->getParameter('domjudge.authmethods');
        }
        $auth_allow_xheaders  = in_array('xheaders', $authmethods);
        $auth_allow_ipaddress = in_array('ipaddress', $authmethods);

        if ($token instanceof AlternateLoginToken && $token->getUser() == null) {
          throw new CustomUserMessageAuthenticationException("No user matching your ip address({$token->getIpAddress()}) found.");
        }
        try {
            $user = $userProvider->loadUserByUsername($token->getUsername());
        } catch (UsernameNotFoundException $e) {
            // CAUTION: this message will be returned to the client
            // (so don't put any un-trusted messages / error strings here)
            throw new CustomUserMessageAuthenticationException('Invalid username or password');
        }
        $tokenValid = false;
        if ($token instanceof UsernamePasswordToken) {
          $tokenValid = $this->encoder->isPasswordValid($user, $token->getCredentials());

          // Lets update the ipaddress if we support that auth method
          if ($tokenValid && $auth_allow_ipaddress && $user->getIpaddress() == null) {
            $user->setIpAddress($clientIP);
            $this->em->flush($user);
          }
        } elseif ($token instanceof AlternateLoginToken) {
          $tokenValid = true;
        }

        if ($tokenValid) {
            return new UsernamePasswordToken(
                $user,
                $user->getPassword(),
                $providerKey,
                $user->getRoles()
            );
        }

        // CAUTION: this message will be returned to the client
        // (so don't put any un-trusted messages / error strings here)
        throw new CustomUserMessageAuthenticationException('Invalid username or password');
    }

    public function supportsToken(TokenInterface $token, $providerKey)
    {
        return ($token instanceof UsernamePasswordToken && $token->getProviderKey() === $providerKey)
        || $token instanceof AlternateLoginToken;
    }

    public function createToken(Request $request, $username, $password, $providerKey)
    {
        if ($this->container->hasParameter('domjudge.authmethods') == false) {
          return new UsernamePasswordToken($username, $password, $providerKey);
        }

        // Figure out what auth methods we will support
        $authmethods = $this->container->getParameter('domjudge.authmethods');
        $auth_allow_xheaders  = in_array('xheaders', $authmethods);
        $auth_allow_ipaddress = in_array('ipaddress', $authmethods);

        //----------------------------------------------------------------------
        // IP Based Authentication
        if ($auth_allow_ipaddress && $request->request->get('loginmethod') == 'ipaddress') {
          $clientIP = $this->container->get('request_stack')->getMasterRequest()->getClientIp();
          $user = $this->em->getRepository('DOMJudgeBundle:User')->findOneBy(['ipaddress' => $clientIP, 'username' => $request->request->get('_username')]);
          $token = new AlternateLoginToken();
          $token->setIpAddress($clientIP);
          if ($user) {
            $token->setUser($user);
          }
          return $token;
        }

        //----------------------------------------------------------------------
        // X-Headers based authentication
        if ($auth_allow_xheaders && $request->request->get('loginmethod') == "xheaders" && $request->headers->get('X-DOMjudge-Autologin')) {
          $username = trim($request->headers->get('X-DOMjudge-Login'));
          $password = base64_decode(trim($request->headers->get('X-DOMjudge-Pass')));
          return new UsernamePasswordToken($username, $password, $providerKey);
        }

        // Form based authentication
        return new UsernamePasswordToken($username, $password, $providerKey);
    }
}
