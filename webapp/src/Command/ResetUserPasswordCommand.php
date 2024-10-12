<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'domjudge:reset-user-password',
    description: 'Reset the password of the given user'
)]
class ResetUserPasswordCommand extends Command
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'username',
                InputArgument::REQUIRED,
                'The username of the user to reset the password of'
            )
            ->addArgument(
                'password',
                InputArgument::OPTIONAL,
                'The new password; if not provided a random password is generated'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $username = $input->getArgument('username');
        $user = $this->em
            ->getRepository(User::class)
            ->findOneBy(['username' => $username]);

        if (!$user) {
            $style->error('Can not find user with username ' . $username);
            return Command::FAILURE;
        }

        $password = $input->getArgument('password') ?? Utils::generatePassword();

        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $password)
        );
        $this->em->flush();

        $style->success('New password for ' . $username . ' is ' . $password);

        return Command::SUCCESS;
    }
}
