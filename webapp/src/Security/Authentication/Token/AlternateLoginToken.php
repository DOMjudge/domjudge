<?php declare(strict_types=1);
namespace App\Security\Authentication\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

class AlternateLoginToken extends AbstractToken
{
    private $ipaddress;
    public function __construct(array $roles = array())
    {
        parent::__construct($roles);

        // If the user has roles, consider it authenticated
        $this->setAuthenticated(count($roles) > 0);
    }

    public function getCredentials()
    {
        return '';
    }

    public function getIpAddress() {
        return $this->ipaddress;
    }
    public function setIpAddress(string $ipaddress) {
        $this->ipaddress = $ipaddress;
    }
}
