<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\TeamCategory;
use App\Service\AwardService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\SubmissionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class TwigGlobalsExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @param array<int, bool> $renderedSources
     */
    public function __construct(
        protected readonly DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly Environment $twig,
        protected readonly EntityManagerInterface $em,
        protected readonly SubmissionService $submissionService,
        protected readonly EventLogService $eventLogService,
        protected readonly AwardService $awards,
        protected readonly TokenStorageInterface $tokenStorage,
        protected readonly AuthorizationCheckerInterface $authorizationChecker,
        protected readonly RouterInterface $router,
        protected readonly SerializerInterface $serializer,
        #[Autowire('%kernel.project_dir%')]
        protected readonly string $projectDir,
        protected array $renderedSources = []
    ) {}

    public function getGlobals(): array
    {
        $refresh_cookie = $this->dj->getCookie("domjudge_refresh");
        $refresh_flag   = ($refresh_cookie == null || (bool)$refresh_cookie);

        $user = $this->dj->getUser();
        $team = $user?->getTeam();

        $selfRegistrationCategoriesCount = $this->em->getRepository(TeamCategory::class)->count(['allow_self_registration' => 1]);
        // These variables mostly exist for the header template.
        $currentContest = $this->dj->getCurrentContest();
        return [
            'current_contest_id'            => $this->dj->getCurrentContestCookie(),
            'current_contest'               => $currentContest,
            'current_contests'              => $this->dj->getCurrentContests(),
            'current_public_contest'        => $this->dj->getCurrentContest(onlyPublic: true),
            'current_public_contests'       => $this->dj->getCurrentContests(onlyPublic: true),
            'have_printing'                 => $this->config->get('print_command'),
            'show_languages_to_teams'       => $this->config->get('show_language_versions'),
            'refresh_flag'                  => $refresh_flag,
            'icat_url'                      => $this->config->get('icat_url'),
            'external_ccs_submission_url'   => $this->config->get('external_ccs_submission_url'),
            'current_team_contest'          => $team ? $this->dj->getCurrentContest($team->getTeamid()) : null,
            'current_team_contests'         => $team ? $this->dj->getCurrentContests($team->getTeamid()) : null,
            'submission_languages'          => $this->dj->getAllowedLanguagesForContest($currentContest),
            'alpha3_countries'              => Countries::getAlpha3Names(),
            'alpha3_alpha2_country_mapping' => array_combine(
                Countries::getAlpha3Codes(),
                array_map(Countries::getAlpha2Code(...), Countries::getAlpha3Codes())
            ),
            'show_shadow_differences'       => $this->tokenStorage->getToken() &&
                $this->authorizationChecker->isGranted('ROLE_ADMIN') &&
                $this->dj->shadowMode(),
            'doc_links'                     => $this->dj->getDocLinks(),
            'allow_registration'            => $selfRegistrationCategoriesCount !== 0,
            'enable_ranking'                => $this->config->get('enable_ranking'),
            'editor_themes'                 => [
                'vs'                        => ['name' => 'Visual Studio (light)'],
                'vs-dark'                   => ['name' => 'Visual Studio (dark)'],
                'Solarized-dark'            => ['name' => 'Solarized (dark)', 'external' => true],
                'Solarized-light'           => ['name' => 'Solarized (light)', 'external' => true],
                'Tomorrow-Night-Blue'       => ['name' => 'Tomorrow Night Blue', 'external' => true],
                'Tomorrow-Night-Bright'     => ['name' => 'Tomorrow Night Bright', 'external' => true],
                'Tomorrow-Night-Eighties'   => ['name' => 'Tomorrow Night Eighties', 'external' => true],
                'Tomorrow-Night'            => ['name' => 'Tomorrow Night', 'external' => true],
                'Tomorrow'                  => ['name' => 'Tomorrow', 'external' => true],
                'hc-light'                  => ['name' => 'High contrast (light)'],
                'hc-black'                  => ['name' => 'High contrast (dark)'],
            ],
            'diff_modes'                    => [
                'side-by-side'              => ["name"  => "Side-by-side"],
                'inline'                    => ["name"  => "Inline"],
            ],
            'can_change_password'           => $this->dj->canChangePassword(),
        ];
    }
}
