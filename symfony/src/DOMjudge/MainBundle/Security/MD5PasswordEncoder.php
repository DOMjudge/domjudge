<?php

namespace DOMjudge\MainBundle\Security;

use Symfony\Component\Security\Core\Encoder\BasePasswordEncoder;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class MD5PasswordEncoder extends BasePasswordEncoder
{

	/**
	 * Encodes the raw password.
	 *
	 * @param string $raw The password to encode
	 * @param string $salt The salt
	 *
	 * @return string The encoded password
	 */
	public function encodePassword($raw, $salt)
	{
		if ( $this->isPasswordTooLong($raw) ) {
			throw new BadCredentialsException('Invalid password.');
		}

		return md5($salt . "#" . $raw);
	}

	/**
	 * Checks a raw password against an encoded password.
	 *
	 * @param string $encoded An encoded password
	 * @param string $raw A raw password
	 * @param string $salt The salt
	 *
	 * @return bool true if the password is valid, false otherwise
	 */
	public function isPasswordValid($encoded, $raw, $salt)
	{
		if ( $this->isPasswordTooLong($raw) ) {
			throw new BadCredentialsException('Invalid password.');
		}

		return md5($salt . "#" . $raw) === $encoded;
	}
}
