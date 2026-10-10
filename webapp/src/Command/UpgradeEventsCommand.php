<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Contest;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\CcsApiVersion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'domjudge:upgrade-events',
    description: 'Upgrade the events of all contests to a CCS API version and switch to that version'
)]
readonly class UpgradeEventsCommand
{
    public function __construct(
        protected EntityManagerInterface $em,
        protected ConfigurationService $config,
        protected EventLogService $eventLogService,
        protected DOMJudgeService $dj,
    ) {
    }

    public function __invoke(
        SymfonyStyle $style,
        #[Argument(description: 'The CCS API version to upgrade to; defaults to the configured version')]
        ?string $version = null,
    ): int {
        /** @var CcsApiVersion $configuredVersion */
        $configuredVersion = $this->config->get('ccs_api_version');
        $version = $version === null ? $configuredVersion : CcsApiVersion::tryFrom($version);
        if ($version === null) {
            $style->error(sprintf(
                'Unknown version, use one of: %s',
                implode(', ', array_map(fn(CcsApiVersion $version) => $version->value, CcsApiVersion::cases()))
            ));
            return Command::FAILURE;
        }
        $contentVersion = $version->getContentVersion();

        $contestIds = $this->em->createQueryBuilder()
            ->from(Contest::class, 'c')
            ->select('c.cid, c.externalid')
            ->getQuery()
            ->getArrayResult();

        $numUpgraded = 0;
        $alreadyUpgraded = [];
        foreach ($contestIds as ['cid' => $contestId, 'externalid' => $externalId]) {
            // Upgrading clears the entity manager, so get a fresh reference every time.
            $contest = $this->em->getReference(Contest::class, $contestId);
            $result = $this->eventLogService->upgradeEvents($contest, $contentVersion);
            if ($result === null) {
                if ($this->eventLogService->hasEvents($contest, $contentVersion)) {
                    $alreadyUpgraded[] = $externalId;
                }
                continue;
            }
            $numUpgraded++;
            $style->writeln(sprintf(
                'Contest %s: upgraded %d events from version %s to %s and kept %d events written in %s.',
                $externalId, $result['copied'], $result['from']->value, $contentVersion->value,
                $result['kept'], $contentVersion->value
            ));
        }

        if (!empty($alreadyUpgraded)) {
            $style->warning(sprintf(
                'Events of contests %s are already in version %s, nothing to upgrade.',
                implode(', ', $alreadyUpgraded),
                $version->value
            ));
        }

        if ($version !== $configuredVersion) {
            $this->config->saveChanges(
                array_merge($this->config->all(), ['ccs_api_version' => $version->value]),
                $this->eventLogService,
                $this->dj
            );
        }

        $style->success(sprintf(
            $numUpgraded === 0
                ? 'No events were upgraded; the CCS API version is set to %s.'
                : 'Events are upgraded and the CCS API version is set to %s.',
            $version->value
        ));
        return Command::SUCCESS;
    }
}
