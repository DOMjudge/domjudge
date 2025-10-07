<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Language;
use App\Entity\Version;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_JURY')]
#[Route(path: '/jury/versions')]
class VersionController extends BaseController
{
    public function __construct(
        EntityManagerInterface $em,
        protected readonly EventLogService $eventLogService,
        DOMJudgeService $dj,
        KernelInterface $kernel,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    /**
     * @return array{
     *     data: list<array{
     *         language: Language,
     *         compiler_outputs: array<string, array{command: string, version: string, hostdata: list<mixed>, versionid: int}>,
     *         canonical_compiler_key: string,
     *         canonical_runner_key: string,
     *         runner_outputs: array<string, array{command: string, version: string, hostdata: list<mixed>, versionid: int}>
     *     }>
     * }
     */
    #[Route(path: '', name: 'jury_versions')]
    #[Template(template: 'jury/versions.html.twig')]
    public function indexAction(): array
    {
        /** @var Language[] $languages */
        $languages = $this->em->createQueryBuilder()
            ->select('l', 'v', 'jh')
            ->from(Language::class, 'l')
            ->leftJoin('l.versions', 'v', 'WITH', 'v.active = 1')
            ->leftJoin('v.judgehost', 'jh')
            ->andWhere('l.allowSubmit = 1')
            ->getQuery()->getResult();

        $data = [];
        foreach ($languages as $language) {
            $compilerOutputs = [];
            $runnerOutputs = [];

            foreach ($language->getVersions() as $version) {
                /** @var Version $version */
                $compilerCommand = $version->getCompilerVersionCommand();
                $compilerVersion = $version->getCompilerVersion();
                $key = $compilerCommand . '###' . $compilerVersion;
                if (!array_key_exists($key, $compilerOutputs)) {
                    $compilerOutputs[$key] = [
                        'command' => $compilerCommand,
                        'version' => $compilerVersion,
                        'hostdata' => [],
                        'versionid' => $version->getVersionid(),
                    ];
                }
                $compilerOutputs[$key]['hostdata'][] = [
                    'hostname' => $version->getJudgehost()->getHostname(),
                    'last_changed' => $version->getLastChangedTime(),
                ];

                $runnerCommand = $version->getRunnerVersionCommand();
                $runnerVersion = $version->getRunnerVersion();
                if (!empty($runnerVersion) && !empty($runnerCommand)) {
                    $key = $runnerCommand . '###' . $runnerVersion;
                    if (!array_key_exists($key, $runnerOutputs)) {
                        $runnerOutputs[$key] = [
                            'command' => $runnerCommand,
                            'version' => $runnerVersion,
                            'hostdata' => [],
                            'versionid' => $version->getVersionid(),
                        ];
                    }
                    $runnerOutputs[$key]['hostdata'][] = [
                        'hostname' => $version->getJudgehost()->getHostname(),
                        'last_changed' => $version->getLastChangedTime(),
                    ];
                }
            }
            $canonicalCompilerKey = '';
            if (!empty($language->getCompilerVersionCommand()) && !empty($language->getCompilerVersion())) {
                $canonicalCompilerKey = $language->getCompilerVersionCommand() . '###' . $language->getCompilerVersion();
            }
            $canonicalRunnerKey = '';
            if (!empty($language->getRunnerVersionCommand()) && !empty($language->getRunnerVersion())) {
                $canonicalRunnerKey = $language->getRunnerVersionCommand() . '###' . $language->getRunnerVersion();
            }
            $data[] = [
                'language' => $language,
                'compiler_outputs' => $compilerOutputs,
                'canonical_compiler_key' => $canonicalCompilerKey,
                'canonical_runner_key' => $canonicalRunnerKey,
                'runner_outputs' => $runnerOutputs,
            ];
        }

        return ['data' => $data];
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/{versionId<\d+>}/promote_compiler', name: 'jury_compiler_promote')]
    public function promoteCompilerAction(int $versionId): Response
    {
        $version = $this->em->getRepository(Version::class)->find($versionId);
        if (!$version) {
            throw new NotFoundHttpException(sprintf('Version with ID %s not found', $versionId));
        }

        $language = $version->getLanguage();
        $language
            ->setCompilerVersion($version->getCompilerVersion())
            ->setCompilerVersionCommand($version->getCompilerVersionCommand());
        $this->em->flush();

        return $this->redirectToRoute('jury_versions');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/{versionId<\d+>}/promote_runner', name: 'jury_runner_promote')]
    public function promoteRunnerAction(int $versionId): Response
    {
        $version = $this->em->getRepository(Version::class)->find($versionId);
        if (!$version) {
            throw new NotFoundHttpException(sprintf('Version with ID %s not found', $versionId));
        }

        $language = $version->getLanguage();
        $language
            ->setRunnerVersion($version->getRunnerVersion())
            ->setRunnerVersionCommand($version->getRunnerVersionCommand());
        $this->em->flush();

        return $this->redirectToRoute('jury_versions');
    }
}
