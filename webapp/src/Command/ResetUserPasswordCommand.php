<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Class ResetUserPasswordCommand
 *
 * @package App\Command
 */
class ResetUserPasswordCommand extends Command
{
    const STATUS_OK = 0;
    const STATUS_ERROR = 1;

    protected EntityManagerInterface $em;
    protected UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
        $this->em = $em;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this
            ->setName('domjudge:reset-user-password')
            ->setDescription('Reset the password of the given user')
            ->addArgument(
                'username',
                InputArgument::REQUIRED,
                'The username of the user to reset the password of'
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
            return static::STATUS_ERROR;
        }

        $password = Utils::generatePassword();

        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $password)
        );
        $this->em->flush();

        $style->success('New password for ' . $username . ' is ' . $password);

        return static::STATUS_OK;
    }
}
