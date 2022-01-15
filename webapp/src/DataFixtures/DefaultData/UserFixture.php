<?php declare(strict_types=1);

namespace App\DataFixtures\DefaultData;

use App\Entity\User;
use App\Service\DOMjudgeService;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserFixture extends AbstractDefaultDataFixture implements DependentFixtureInterface
{
    /**
     * @var DOMjudgeService
     */
    protected $dj;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var UserPasswordEncoderInterface
     */
    protected $passwordEncoder;

    /**
     * @var bool
     */
    protected $debug;

    public function __construct(DOMjudgeService $dj, LoggerInterface $logger, UserPasswordEncoderInterface $passwordEncoder, bool $debug)
    {
        $this->dj              = $dj;
        $this->logger          = $logger;
        $this->passwordEncoder = $passwordEncoder;
        $this->debug           = $debug;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function load(ObjectManager $manager)
    {
        if (!($adminUser = $manager->getRepository(User::class)->findOneBy(['username' => 'admin']))) {
            $adminPasswordFile = sprintf(
                '%s/%s',
                $this->dj->getDOMjudgeEtcDir(),
                'initial_admin_password.secret'
            );

            $adminpasswordContents = file_get_contents($adminPasswordFile);
            if ($adminpasswordContents === false) {
                throw new Exception(sprintf('Can not read admin password from %s', $adminPasswordFile));
            }

            $adminUser = new User();
            $adminUser
                ->setUsername('admin')
                ->setName('Administrator')
                ->setPassword($this->passwordEncoder->encodePassword($adminUser, trim($adminpasswordContents)))
                ->addUserRole($this->getReference(RoleFixture::ADMIN_REFERENCE));
            if ($this->debug) {
                $domjudgeTeam = $this->getReference(TeamFixture::DOMJUDGE_REFERENCE);
                $adminUser
                    ->setTeam($domjudgeTeam)
                    ->addUserRole($this->getReference(RoleFixture::TEAM_REFERENCE));
            };
            $manager->persist($adminUser);
        } else {
            $this->logger->info('User admin already exists, not created');
        }

        if (!($judgehostUser = $manager->getRepository(User::class)->findOneBy(['username' => 'judgehost']))) {
            $judgehostUser = new User();
            $judgehostUser
                ->setUsername('judgehost')
                ->setName('User for judgedaemons')
                ->setPassword($this->passwordEncoder->encodePassword($judgehostUser, $this->getRestapiPassword()))
                ->addUserRole($this->getReference(RoleFixture::JUDGEHOST_REFERENCE));
            $manager->persist($judgehostUser);
        } else {
            $this->logger->info('User judgehost already exists, not created');
        }

        $manager->flush();
    }

    /**
     * Get the password for the REST API
     *
     * @return string
     * @throws Exception
     */
    protected function getRestapiPassword(): string
    {
        $restapiCredentialsFile = sprintf(
            '%s/%s',
            $this->dj->getDOMjudgeEtcDir(),
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

    /**
     * @inheritDoc
     */
    public function getDependencies()
    {
        return [RoleFixture::class];
    }
}
