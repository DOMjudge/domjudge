<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'domjudge:reset-user-password',
    description: 'Reset the password of the given user'
)]
readonly class ResetUserPasswordCommand
{
    public function __construct(
        protected EntityManagerInterface $em,
        protected UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function __invoke(
        #[Argument(description: 'The username of the user to reset the password of')]
        string $username,
        #[Argument(description: 'The new password; if not provided a random password is generated')]
        ?string $password,
        SymfonyStyle $style
    ): int {
        $user = $this->em
            ->getRepository(User::class)
            ->findOneBy(['username' => $username]);
        if (!$user) {
            $style->error('Can not find user with username ' . $username);
            return Command::FAILURE;
        }
        $password ??= Utils::generatePassword();
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $password)
        );
        $this->em->flush();
        $style->success('New password for ' . $username . ' is ' . $password);
        return Command::SUCCESS;
    }
}
