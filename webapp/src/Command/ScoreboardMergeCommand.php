<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Problem;
use App\Entity\RankCache;
use App\Entity\ScoreCache;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\ScoreboardService;
use App\Utils\FreezeData;
use App\Utils\Scoreboard\Scoreboard;
use App\Utils\Utils;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use ZipArchive;

#[AsCommand(
    name: 'scoreboard:merge',
    description: 'Merges scoreboards from multiple sites from API endpoints.'
)]
readonly class ScoreboardMergeCommand
{
    public function __construct(
        protected DOMJudgeService $dj,
        protected ConfigurationService $config,
        protected Environment $twig,
        protected HttpClientInterface $client,
        protected ScoreboardService $scoreboardService,
        protected RouterInterface $router,
        #[Autowire('%kernel.project_dir%')]
        protected string $projectDir,
    ) {
    }

    /**
     * url: "https://judge.gehack.nl/api/v4" or "/path/to/file"
     * endpoint: "/teams"
     * args: "?public=1" (ignored for files)
     * @return array<mixed>
     */
    protected function getEndpoint(
        string $url,
        string $endpoint,
        string $args = ''
    ): array {
        if (str_starts_with($url, 'http')) {
            return $this->client
                ->request('GET', $url . $endpoint . $args)
                ->toArray();
        }
        return json_decode(file_get_contents($url . $endpoint . '.json'), true);
    }

    /**
     * @param list<string> $feedUrl
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function __invoke(
        #[Argument(description: 'Where to store the ZIP file with the merged scoreboard')]
        string $outputFile,
        #[Argument(description: 'Title of the merged contest.')]
        string $contestName,
        #[Argument(description: 'Alternating URL location of the scoreboard to merge and a comma separated list of group_ids to include.' . PHP_EOL .
            'If an URL and it requires authentication, use username:password@ in the URL' . PHP_EOL .
            'URL should have the form https://<domain>/api/v4/contests/<contestid>/ for DOMjudge or point to any ICPC Contest API compatible contest' . PHP_EOL .
            'Only the /teams, /organizations, /problems and /scoreboard endpoint are used, so manually putting files in those locations can work as well.' . PHP_EOL .
            'Alternatively, you can mount local files directly in the container: add "- /path/to/scoreboards:/scoreboards" to "docker-compose.yml" and use "/scoreboards/eindhoven" as path.')]
        array $feedUrl,
        OutputInterface $output,
        SymfonyStyle $style,
        #[Option(description: 'Name of the team category to use', shortcut: 'c')]
        string $category = 'Participant',
    ): int {
        $teams = [];
        $nextTeamId = 0;
        $problems = [];
        $problemNameToIdMap = [];
        $scoreCache = [];
        /** @var RankCache[] $rankCache */
        $rankCache = [];
        $penaltyTime = (int)$this->config->get('penalty_time');
        $scoreIsInSeconds = (bool)$this->config->get('score_in_seconds');
        $timeOfLastCorrect = [];
        $affiliations = [];
        $firstSolve = [];
        $contest = (new Contest())
            ->setName($contestName);
        $freezeData = null;

        $category = (new TeamCategory())
            ->setName($category)
            ->setCategoryid(0);

        // Convert from flat list to list of (url, groups) pairs
        $sites = [];

        if (count($feedUrl) % 2 != 0) {
            $style->error("Provide an even number of arguments: all pairs of url and comma separated group ids.");
            return Command::FAILURE;
        }

        for ($i = 0; $i < count($feedUrl); $i += 2) {
            $site = [];
            $site['path'] = $feedUrl[$i];
            # Some simple validation to make sure we're actually parsing group ids.
            $groupsString = $feedUrl[$i + 1];
            $site['group_ids'] = explode(',', $groupsString);
            $sites[] = $site;
        }

        foreach ($sites as $site) {
            $path = $site['path'];
            // Strip of last /
            if (str_ends_with($path, '/')) {
                $path = substr($path, 0, strlen($path) - 1);
            }

            $teamData = $this->getEndpoint($path, '/teams');
            $organizationData = $this->getEndpoint($path, '/organizations');
            $problemData = $this->getEndpoint($path, '/problems');
            $organizationMap = [];
            foreach ($organizationData as $organization) {
                $organizationMap[$organization['id']] = $organization;
            }
            $problemMap = [];
            foreach ($problemData as $problem) {
                $problemMap[$problem['id']] = $problem;
            }

            $teamIdMap = [];
            foreach ($teamData as $team) {
                // Only include the team if its id is listed in the corresponding groups list.
                $include = false;
                foreach ($team['group_ids'] as $group_id) {
                    if (in_array($group_id, $site['group_ids'])) {
                        $include = true;
                        break;
                    }
                }

                if (!$include) {
                    continue;
                }

                $teamObj = (new Team())
                    ->setName($team['name'])
                    ->setDisplayName($team['display_name'] ?? $team['name'])
                    ->setEnabled(true);
                if ($team['organization_id'] !== null &&
                    isset($organizationMap[$team['organization_id']])) {
                    $organization = $organizationMap[$team['organization_id']];
                    $organizationName = $organization['formal_name'] ?? $organization['name'];

                    if (!array_key_exists($organizationName, $affiliations)) {
                        $affiliation = (new TeamAffiliation())
                            ->setName($organizationName)
                            ->setAffilid(count($affiliations));
                        $affiliations[$organizationName] = $affiliation;
                    }
                    $teamObj->setAffiliation($affiliations[$organizationName]);
                }

                $teamObj->addCategory($category);
                $oldid = $team['id'];
                $newid = $nextTeamId++;
                $teamObj->setTeamid($newid);
                $teams[] = $teamObj;
                $teamIdMap[$oldid] = $newid;
            }

            $scoreboardData = $this->getEndpoint(
                $path,
                '/scoreboard',
                '?public=1'
            );

            if (!$contest->getStarttimeString()) {
                $state = $scoreboardData['state'];
                $endtime = $state['ended'] ?? $state['started'];
                // While the contest is running, simply use the start time for everything.
                $contest
                    ->setStarttimeString($state['started'])
                    ->setEndtimeString($endtime)
                    ->setFreezetimeString($endtime)
                    ->setUnfreezetimeString($endtime)
                    ->setFinalizetime($endtime)
                    ->setDeactivatetimeString($endtime)
                    ->updateTimes();
            }
            $freezeData = new FreezeData($contest);

            // Add scoreboard data
            foreach ($scoreboardData['rows'] as $row) {
                // If this this team is not in the teams array (because it's not in the right group),
                // ignore this row.
                if (!array_key_exists($row['team_id'], $teamIdMap)) {
                    continue;
                }
                $team = $teams[$teamIdMap[$row['team_id']]];
                foreach ($row['problems'] as $problem) {
                    // Problems are keyed by name, as that seems to be more consistent.
                    // Some sites occasionally mix up short_name and id.
                    $problemId = $problem['problem_id'];
                    $baseProblem = $problemMap[$problemId];
                    $label = $baseProblem['label'];
                    $name = $baseProblem['name'];
                    if (!array_key_exists($name, $problemNameToIdMap)) {
                        $id = count($problems);
                        $problemObj = (new Problem())
                            ->setProbid($id)
                            ->setExternalid((string)$id)
                            ->setName($name);
                        $contestProblemObj = (new ContestProblem())
                            ->setProblem($problemObj)
                            ->setColor($baseProblem['color'])
                            ->setShortName($label);
                        $problems[$id] = $contestProblemObj;
                        $problemNameToIdMap[$name] = $id;
                        $firstSolve[$name] = null;
                    } else {
                        $id = $problemNameToIdMap[$name];
                    }
                    $scoreCacheObj = (new scoreCache())
                        ->setProblem($problems[$id]->getProblem())
                        ->setTeam($team);
                    if (array_key_exists('time', $problem)) {
                        // TODO: Make this work with input in seconds as well.
                        $scoreCacheObj
                            ->setSolveTimePublic($problem['time'] * 60)
                            ->setSolveTimeRestricted($problem['time'] * 60);
                        if ($firstSolve[$name] === null ||
                            $problem['time'] * 60 < $firstSolve[$name]) {
                            $firstSolve[$name] = $problem['time'] * 60;
                        }
                    }
                    $scoreCacheObj
                        ->setSubmissionsPublic($problem['num_judged'])
                        ->setSubmissionsRestricted($problem['num_judged'])
                        ->setIsCorrectPublic($problem['solved'])
                        ->setIsCorrectRestricted($problem['solved']);
                    $scoreCache[] = $scoreCacheObj;
                }
            }
        }

        // Update the first to solve fields.
        foreach ($scoreCache as &$scoreCacheObj) {
            if ($scoreCacheObj->getSolveTimeRestricted() == $firstSolve[$scoreCacheObj->getProblem()->getName()]) {
                $scoreCacheObj->setIsFirstToSolve(true);
            }

            $teamId = $scoreCacheObj->getTeam()->getTeamid();
            if (isset($rankCache[$teamId])) {
                $rankCacheObj = $rankCache[$teamId];
            } else {
                $rankCacheObj = (new RankCache())
                    ->setTeam($scoreCacheObj->getTeam());
                $rankCache[$teamId] = $rankCacheObj;
            }

            $problem = $problems[$scoreCacheObj->getProblem()->getProbid()];
            if ($scoreCacheObj->getIsCorrectRestricted()) {
                $rankCacheObj->setPointsRestricted($rankCacheObj->getPointsRestricted() + $problem->getPoints());
                $solveTime = Utils::scoretime(
                    (float)$scoreCacheObj->getSolvetimeRestricted(),
                    $scoreIsInSeconds
                );
                $penalty = Utils::calcPenaltyTime($scoreCacheObj->getIsCorrectRestricted(),
                    $scoreCacheObj->getSubmissionsRestricted(),
                    $penaltyTime, $scoreIsInSeconds);
                $rankCacheObj->setTotaltimeRestricted($rankCacheObj->getTotaltimeRestricted() + $solveTime + $penalty);
                $rankCacheObj->setTotalruntimeRestricted($rankCacheObj->getTotalruntimeRestricted() + $scoreCacheObj->getRuntimeRestricted());
                $timeOfLastCorrect[$teamId] = max(
                    $timeOfLastCorrect[$teamId] ?? 0,
                    Utils::scoretime(
                        (float)$scoreCacheObj->getSolvetimeRestricted(),
                        $scoreIsInSeconds
                    ),
                );
            }
        }

        foreach ($rankCache as $rankCacheObj) {
            $teamId = $rankCacheObj->getTeam()->getTeamid();
            $rankCacheObj->setSortKeyRestricted(ScoreboardService::getICPCScoreKey(
                $rankCacheObj->getPointsRestricted(),
                $rankCacheObj->getTotaltimeRestricted(), $timeOfLastCorrect[$teamId] ?? 0
            ));
        }

        usort($teams, function (Team $a, Team $b) use ($rankCache) {
            $rankCacheA = $rankCache[$a->getTeamid()];
            $rankCacheB = $rankCache[$b->getTeamid()];
            $rankCacheSort = $rankCacheB->getSortKeyRestricted() <=> $rankCacheA->getSortKeyRestricted();
            if ($rankCacheSort === 0) {
                return $a->getEffectiveName() <=> $b->getEffectiveName();
            }

            return $rankCacheSort;
        });

        $scoreboard = new Scoreboard(
            $contest,
            $teams,
            [$category],
            $problems,
            $scoreCache,
            array_values($rankCache),
            $freezeData,
            false,
            (int)$this->config->get('penalty_time'),
            false
        );

        // Render the scoreboard to HTML and print it.
        $data = $this->scoreboardService->getScoreboardTwigData(
            null, null, '', false, true, true, $contest, $scoreboard
        );
        $data['hide_menu'] = true;
        $data['current_contest'] = $contest;

        $output = $this->twig->render('public/scoreboard.html.twig', $data);
        // What files to add to the ZIP file
        $filesToAdd = [
            'webfonts/*',
            'images/*'
        ];
        // Detect other files to add to the ZIP file by scanning the output.
        // We need to do this anyway, since we need to rewrite the absolute paths.
        $rootUrl = $this->router->generate('root');
        // Parts of the output we should match and what to replace it with.
        // ROOT_URL will be replaced with the root URL as defined above
        $toMatch = [
            '/href="ROOT_URL(.*)(?:\?.*)"/' => 'href="$1"',
            '/src="ROOT_URL(.*)(?:\?.*)"/'  => 'src="$1"',
        ];
        foreach ($toMatch as $pattern => $replace) {
            $pattern = str_replace(
                'ROOT_URL', preg_quote($rootUrl, '/'), $pattern
            );
            preg_match_all($pattern, $output, $matches);
            $filesToAdd = array_merge($filesToAdd, $matches[1]);
            $output = preg_replace($pattern, $replace, $output);
        }

        $zip = new ZipArchive();
        $result = $zip->open($outputFile,
                             ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            $style->error('Can not open output file to write ZIP to: ' . $result);
            return Command::FAILURE;
        }
        $zip->addFromString('index.html', $output);

        // Now add all files we need
        $publicDir = realpath(sprintf('%s/public/', $this->projectDir));
        foreach ($filesToAdd as $fileToAdd) {
            $finder = new Finder();
            $lastSlash = strrpos($fileToAdd, '/');
            if ($lastSlash === false) {
                $path = '';
                $file = $fileToAdd;
            } else {
                $path = substr($fileToAdd, 0, $lastSlash);
                $file = substr($fileToAdd, $lastSlash + 1);
            }
            $pathRegex = sprintf('/^%s/', preg_quote($path, '/'));
            /** @var SplFileInfo $fileInfo */
            foreach ($finder->followLinks()->in($publicDir)->path($pathRegex)->name($file)->files() as $fileInfo) {
                $zip->addFile($fileInfo->getRealPath(),
                              $fileInfo->getRelativePathname());
            }
        }

        $zip->close();

        $style->success(sprintf('Merged scoreboard data written to %s', $outputFile));
        return Command::SUCCESS;
    }
}
