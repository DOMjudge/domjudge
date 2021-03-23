<?php declare(strict_types=1);

namespace App\DataFixtures\DefaultData;

use App\Entity\User;
use App\Service\DOMJudgeService;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserFixture extends AbstractDefaultDataFixture implements DependentFixtureInterface
{
    /**
     * @var DOMJudgeService
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

    public function __construct(DOMJudgeService $dj, LoggerInterface $logger, UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->dj              = $dj;
        $this->logger          = $logger;
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function load(ObjectManager $manager)
    {
        if (!($adminuser = $manager->getRepository(User::class)->findOneBy(['username' => 'admin']))) {
            $adminPasswordFile = sprintf(
                '%s/%s',
                $this->dj->getDomjudgeEtcDir(),
                'initial_admin_password.secret'
            );

            $adminpasswordContents = file_get_contents($adminPasswordFile);
            if ($adminpasswordContents === false) {
                throw new Exception(sprintf('Can not read admin password from %s', $adminPasswordFile));
            }

            $adminuser = new User();
            $adminuser
                ->setUsername('admin')
                ->setName('Administrator')
                ->setPassword($this->passwordEncoder->encodePassword($adminuser, trim($adminpasswordContents)))
                ->addUserRole($this->getReference(RoleFixture::ADMIN_REFERENCE));
            $manager->persist($adminuser);
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

    /**
     * @inheritDoc
     */
    public function getDependencies()
    {
        return [RoleFixture::class];
    }
}
