<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Problem;
use App\Entity\ScoreCache;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Service\DOMJudgeService;
use App\Utils\FreezeData;
use App\Utils\Scoreboard\Scoreboard;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


/**
 * Class ScoreboardMergeCommand
 * @package App\Command
 */
class ScoreboardMergeCommand extends Command
{
    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var HttpClientInterface
     */
    protected $client;

    /**
     * ScoreboardMergeCommand constructor.
     * @param DOMJudgeService     $dj
     * @param Environment         $twig
     * @param HttpClientInterface $client
     * @param string|null         $name
     */
    public function __construct(
        DOMJudgeService $dj,
        Environment $twig,
        HttpClientInterface $client,
        string $name = null
    ) {
        parent::__construct($name);
        $this->dj = $dj;
        $this->twig = $twig;
        $this->client = $client;
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('scoreboard:merge')
            ->setDescription('Merges scoreboards from multiple sites from API endpoints.')
            ->setHelp('Usage example: scoreboard:merge "BAPC preliminaries" ' .
                      'https://judge.gehack.nl/api/v4/contests/3/ 3 ' .
                      'http://ragnargrootkoerkamp.nl/upload/uva 2' . PHP_EOL . PHP_EOL .
                      'This fetches teams and scoreboard data from API endpoints and prints a merged HTML scoreboard. It assumes times in minutes.'
            )
            ->addOption(
                'category',
                'c',
                InputOption::VALUE_REQUIRED,
                'Name of the team category to use',
                'Participant'
            )
            ->addArgument(
                'contest-name',
                InputArgument::REQUIRED,
                'Title of the merged contest.'
            )
            ->addArgument(
                'feed-url',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'Alternating URL location of the scoreboard to merge and a comma separated list of group_ids to include.' . PHP_EOL .
                'If an URL and it requires authentication, use username:password@ in the URL' . PHP_EOL .
                'URL should have the form https://<domain>/api/v4/contests/<contestid>/ for DOMjudge or point to any ICPC Contest API compatible contest' . PHP_EOL .
                'Only the /teams, /organizations, /problems and /scoreboard endpoint are used, so manually putting files in those locations can work as well.'
            );
    }

    /**
     * @inheritdoc
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $style = new SymfonyStyle($input, $output);
        $teams = [];
        $nextTeamId = 0;
        $problems = [];
        $problemIdMap = [];
        $scoreCache = [];
        $affiliations = [];
        $firstSolve = [];
        $contest = (new Contest())
            ->setName($input->getArgument('contest-name'));
        $freezeData = null;

        $category = (new TeamCategory())
            ->setName($input->getOption('category'))
            ->setCategoryid(0);

        $siteArguments = $input->getArgument('feed-url');

        // Convert from flat list to list of (url, groups) pairs
        $sites = [];

        if (count($siteArguments) % 2 != 0) {
            $style->error("Provide an even number of arguments: all pairs of url and comma separated group ids.");
            return 1;
        }

        for ($i = 0; $i < count($siteArguments); $i += 2) {
            $site = [];
            $site['path'] = $siteArguments[$i];
            # Some simple validation to make sure we're actually parsing group ids.
            $groupsString = $siteArguments[$i + 1];
            if (!preg_match('/^\d+(,\d+)*$/', $groupsString)) {
                $style->error('Argument does not look like a comma separated list of group ids: ' . $groupsString);
                return 1;
            }
            $site['group_ids'] = array_map(
                'intval', explode(',', $groupsString)
            );
            $sites[] = $site;
        }

        foreach ($sites as $site) {
            $path = $site['path'];
            // Strip of last /
            if (substr($path, -1) === '/') {
                $path = substr($path, 0, strlen($path) - 1);
            }

            $teamData = $this->client
                ->request('GET', $path . '/teams')->toArray();
            $organizationData = $this->client
                ->request('GET', $path . '/organizations')->toArray();
            $problemData = $this->client
                ->request('GET', $path . '/problems')->toArray();
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

                $teamObj->setCategory($category);
                $oldid = $team['id'];
                $newid = $nextTeamId++;
                $teamObj->setTeamid($newid);
                $teams[] = $teamObj;
                $teamIdMap[$oldid] = $newid;
            }

            $scoreboardData = $this->client
                ->request('GET', $path . '/scoreboard')
                ->toArray();

            if ($contest->getStarttimeString() === null) {
                $state = $scoreboardData['state'];
                $contest
                    ->setStarttimeString($state['started'])
                    ->setEndtimeString($state['ended'])
                    ->setFreezetimeString($state['ended'])
                    ->setUnfreezetimeString($state['ended'])
                    ->setFinalizetime($state['ended'])
                    ->setDeactivatetimeString($state['ended'])
                    ->updateTimes();
                $freezeData = new FreezeData($contest);
            }

            // Add scoreboard data
            foreach ($scoreboardData['rows'] as $row) {
                // If this this team is not in the teams array (because it's not in the right group),
                // ignore this row.
                if (!array_key_exists($row['team_id'], $teamIdMap)) {
                    continue;
                }
                $team = $teams[$teamIdMap[$row['team_id']]];
                foreach ($row['problems'] as $problem) {
                    $problemId = $problem['problem_id'];
                    $label = $problemMap[$problemId]['label'];
                    if (!array_key_exists($label, $problemIdMap)) {
                        $id = count($problems);
                        $problemObj = (new Problem())
                            ->setProbid($id)
                            ->setName($label);
                        $contestProblemObj = (new ContestProblem())
                            ->setProblem($problemObj)
                            ->setShortName($label);
                        $problems[$id] = $contestProblemObj;
                        $problemIdMap[$label] = $id;
                        $firstSolve[$label] = null;
                    } else {
                        $id = $problemIdMap[$label];
                    }
                    $scoreCacheObj = (new scoreCache())
                        ->setProblem($problems[$id]->getProblem())
                        ->setTeam($team);
                    if (array_key_exists('time', $problem)) {
                        // TODO: Make this work with input in seconds as well.
                        $scoreCacheObj
                            ->setSolveTimePublic($problem['time'] * 60)
                            ->setSolveTimeRestricted($problem['time'] * 60);
                        if ($firstSolve[$label] === null or $problem['time'] * 60 < $firstSolve[$label]) {
                            $firstSolve[$label] = $problem['time'] * 60;
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
        }

        $scoreboard = new Scoreboard(
            $contest,
            $teams,
            [$category],
            $problems,
            $scoreCache,
            $freezeData,
            false,
            (int)$this->dj->dbconfig_get('penalty_time', 20),
            false
        );

        // Render the scoreboard to HTML and print it.
        $data = [
            'current_public_contest' => $contest,
            'static'                 => true,
            'hide_menu'              => true,
            'contest'                => $contest,
            'scoreboard'             => $scoreboard,
            'showFlags'              =>
                $this->dj->dbconfig_get('show_flags', true),
            'showAffiliationLogos'   =>
                $this->dj->dbconfig_get('show_affiliation_logos', false),
            'showAffiliations'       =>
                $this->dj->dbconfig_get('show_affiliations', true),
            'showPending'            =>
                $this->dj->dbconfig_get('show_pending', false),
            'showTeamSubmissions'    =>
                $this->dj->dbconfig_get('show_teams_submissions', true),
            'scoreInSeconds'         =>
                $this->dj->dbconfig_get('score_in_seconds', false),
            'maxWidth'               =>
                $this->dj->dbconfig_get('team_column_width', 0),
        ];

        $output = $this->twig->render('public/scoreboard.html.twig', $data);

        // TODO: It would be nice to return a zip containing all relevant css/js/font files.
        echo $output;
        return 0;
    }
}
