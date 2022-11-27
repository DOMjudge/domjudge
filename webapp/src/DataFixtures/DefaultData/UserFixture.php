<?php declare(strict_types=1);

namespace App\DataFixtures\DefaultData;

use App\Entity\User;
use App\Service\DOMJudgeService;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixture extends AbstractDefaultDataFixture implements DependentFixtureInterface
{
    protected DOMJudgeService $dj;
    protected LoggerInterface $logger;
    protected UserPasswordHasherInterface $passwordHasher;
    protected bool $debug;

    public function __construct(DOMJudgeService $dj, LoggerInterface $logger, UserPasswordHasherInterface $passwordHasher, bool $debug)
    {
        $this->dj              = $dj;
        $this->logger          = $logger;
        $this->passwordHasher  = $passwordHasher;
        $this->debug           = $debug;
    }

    public function load(ObjectManager $manager): void
    {
        if (!($adminUser = $manager->getRepository(User::class)->findOneBy(['username' => 'admin']))) {
            $adminPasswordFile = sprintf(
                '%s/%s',
                $this->dj->getDomjudgeEtcDir(),
                'initial_admin_password.secret'
            );

            $adminpasswordContents = file_get_contents($adminPasswordFile);
            if ($adminpasswordContents === false) {
                throw new Exception(sprintf('Can not read admin password from %s', $adminPasswordFile));
            }

            $adminUser = new User();
            $adminUser
                ->setExternalid('admin')
                ->setUsername('admin')
                ->setName('Administrator')
                ->setPassword($this->passwordHasher->hashPassword($adminUser, trim($adminpasswordContents)))
                ->addUserRole($this->getReference(RoleFixture::ADMIN_REFERENCE));
            if ($this->debug) {
                $domjudgeTeam = $this->getReference(TeamFixture::DOMJUDGE_REFERENCE);
                $adminUser
                    ->setTeam($domjudgeTeam)
                    ->addUserRole($this->getReference(RoleFixture::TEAM_REFERENCE));
            }
            $manager->persist($adminUser);
        } else {
            $this->logger->info('User admin already exists, not created');
        }

        if (!($judgehostUser = $manager->getRepository(User::class)->findOneBy(['username' => 'judgehost']))) {
            $judgehostUser = new User();
            $judgehostUser
                ->setExternalid('judgehost')
                ->setUsername('judgehost')
                ->setName('User for judgedaemons')
                ->setPassword($this->passwordHasher->hashPassword($judgehostUser, $this->getRestapiPassword()))
                ->addUserRole($this->getReference(RoleFixture::JUDGEHOST_REFERENCE));
            $manager->persist($judgehostUser);
        } else {
            $this->logger->info('User judgehost already exists, not created');
        }

        $manager->flush();
    }

    protected function getRestapiPassword(): string
    {
        $restapiCredentialsFile = sprintf(
            '%s/%s',
            $this->dj->getDomjudgeEtcDir(),
            'restapi.secret'
        );
        $credentials            = @file($restapiCredentialsFile);
        if (!$credentials) {
            throw new Exception("Can not read judgehost password from $restapiCredentialsFile");
        }
        $lineno = 0;
        foreach ($credentials as $credential) {
            ++$lineno;
            $credential = trim($credential);
            if ($credential === '' || $credential[0] === '#') {
                continue;
            }
            $items = preg_split("/\s+/", $credential);
            if (count($items) !== 4) {
                throw new Exception("Error parsing REST API credentials. Invalid format in line $lineno.");
            }
            list($endpointID, $resturl, $restuser, $restpass) = $items;
            return $restpass;
        }

        throw new Exception("No credentials found in REST API credentials file $restapiCredentialsFile");
    }

    public function getDependencies(): array
    {
        return [RoleFixture::class];
    }
}
