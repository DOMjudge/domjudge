<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Clarification;
use App\Entity\Configuration;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\ExternalJudgement;
use App\Entity\ExternalRun;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\ScoreCache;
use App\Entity\Testcase;
use App\Entity\User;
use App\Service\DOMJudgeService;
use App\Utils\Utils;
use App\Utils\FreezeData;
use App\Utils\Scoreboard\Scoreboard;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Twig\Environment;


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
     * ScoreboardMergeCommand constructor.
     * @param DOMJudgeService        $dj
     * @param bool                   $debug
     * @param string|null            $name
     */
    public function __construct(
        DOMJudgeService $dj,
        bool $debug,
        Environment $twig,
        string $name = null
    ) {
        parent::__construct($name);
        $this->dj                = $dj;
        $this->debug             = $debug;
        $this->twig = $twig;
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('scoreboard:merge')
            ->setDescription('Merges scoreboards from multiple sites from API endpoints.')
            ->setHelp('Usage example: scoreboard:merge "BAPC preliminaries" '.
            'https://judge.gehack.nl/api/v4/contests/3/ 3 '.
            'http://ragnargrootkoerkamp.nl/upload/uva 2' . PHP_EOL . PHP_EOL .

            'This fetches teams and scoreboard data from API endpoints and prints a merged HTML scoreboard. It assumes times in minutes.'
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
                'URL should have the form https://<domain>/api/v4/contests/<contestid>/' . PHP_EOL .
                'Only the /teams and /scoreboard endpoint are used, so manually putting files in those locations can work as well.'
            );
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $teams = [];
        $nextTeamId = 0;
        $problems = [];
        $problemIdMap = [];
        $scoreCache = [];
        $affiliations = [];
        $firstSolve = [];
        $contest = Null;
        $freezeData = new FreezeData($contest);

        $category = new TeamCategory();
        $category->setName("Participants");
        $category->setCategoryid(0);

        $siteArguments = $input->getArgument('feed-url');

        # Convert from flat list to list of (url, groups) pairs
        $sites = [];

        if(count($siteArguments) % 2 != 0){
            echo "Provide an even number of arguments: all pairs of url and comma separated group ids.";
            return 1;
        }

        for($i = 0; $i < count($siteArguments); $i += 2){
            $site = [];
            $site['path'] = $siteArguments[$i];
            # Some simple validation to make sure we're actually parsing group ids.
            $groupsString = $siteArguments[$i+1];
            if(!preg_match('/^\d+(,\d+)*$/', $groupsString)){
                echo 'Argument does not look like a comma separated list of group ids: ' . $groupsString . PHP_EOL;
                return 1;
            }
            $site['group_ids'] = array_map('intval', explode(',', $groupsString));
            $sites[] = $site;
        }

        foreach ($sites as $site){
            $path = $site['path'];

            $client = HttpClient::create();
            $teamData = $client->request('GET', $path.'/teams')->toArray();

            $teamIdMap = [];
            foreach ($teamData as $team){
                # Only include the team if its id is listed in the corresponding groups list.
                $include = false;
                foreach($team['group_ids'] as $group_id){
                    if(in_array($group_id, $site['group_ids'])){
                        $include = true;
                        break;
                    }
                }

                if(!$include){
                    continue;
                }

                $teamObj = new Team();
                $teamObj->setName($team['name']);
                $teamObj->setEnabled(true);
                if(!array_key_exists($team['affiliation'], $affiliations)){
                    $affiliation = new TeamAffiliation();
                    $affiliation->setName($team['affiliation']);
                    $affiliation->setAffilid(count($affiliations));
                    $affiliations[$team['affiliation']] = $affiliation;
                }
                $teamObj->setAffiliation($affiliations[$team['affiliation']]);
                $teamObj->setCategory($category);
                $oldid = $team['id'];
                $newid = $nextTeamId++;
                $teamObj->setTeamid($newid);
                $teams[] = $teamObj;
                $teamIdMap[$oldid] = $newid;
            }

            $scoreboardData = $client->request('GET', $path.'/scoreboard')->toArray();

            if($contest === Null){
                $state = $scoreboardData['state'];
                $contest = new Contest();
                $contest->setName($input->getArgument('contest-name'));
                $contest->setStarttimeString($state['started']);
                $contest->setEndtimeString($state['ended']);
                $contest->setFreezetimeString($state['ended']);
                $contest->setUnfreezetimeString($state['ended']);
                $contest->setFinalizetime($state['ended']);
                $contest->setDeactivatetimeString($state['ended']);
                $contest->UpdateTimes();
            }

            // Add scoreboard data
            foreach ($scoreboardData['rows'] as $row){
                # If this this team is not in the teams array (because it's not in the right group),
                # ignore this row.
                if(!array_key_exists($row['team_id'], $teamIdMap)){
                    continue;
                }
                $team = $teams[$teamIdMap[$row['team_id']]];
                foreach ($row['problems'] as $problem){
                    $label = $problem['label'];
                    if(!array_key_exists($label, $problemIdMap)){
                        $id = count($problems);
                        $problemObj = new Problem();
                        $problemObj->setProbid($id);
                        $problemObj->setName($label);
                        $contestProblemObj = new ContestProblem();
                        $contestProblemObj->setProblem($problemObj);
                        $contestProblemObj->setShortName($label);
                        $problems[$id] = $contestProblemObj;
                        $problemIdMap[$label] = $id;
                        $firstSolve[$label] = Null;
                    } else {
                        $id = $problemIdMap[$label];
                    }
                    $scoreCacheObj = new scoreCache();
                    $scoreCacheObj->setProblem($problems[$id]->getProblem());
                    $scoreCacheObj->setTeam($team);
                    if(array_key_exists('time', $problem)){
                        # TODO: Make this work with input in seconds as well.
                        $scoreCacheObj->setSolveTimePublic($problem['time']*60);
                        $scoreCacheObj->setSolveTimeRestricted($problem['time']*60);
                        if($firstSolve[$label] === Null or $problem['time']*60<$firstSolve[$label]){
                            $firstSolve[$label] = $problem['time']*60;
                        }
                    }
                    $scoreCacheObj->setSubmissionsPublic($problem['num_judged']);
                    $scoreCacheObj->setSubmissionsRestricted($problem['num_judged']);
                    $scoreCacheObj->setIsCorrectPublic($problem['solved']);
                    $scoreCacheObj->setIsCorrectRestricted($problem['solved']);
                    $scoreCache[] = $scoreCacheObj;
                }
            }
        }

        // Update the first to solve fields.
        foreach ($scoreCache as &$scoreCacheObj){
            if($scoreCacheObj->getSolveTimeRestricted() == $firstSolve[$scoreCacheObj->getProblem()->getName()]){
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
            /* jury = */ false,
            $this->dj->dbconfig_get('penalty_time', 20),
            /* scoreIsInSeconds = */ false
        );

        # Render the scoreboard to HTML and print it.
        $data   = [
            'current_public_contest'      => $contest,
            'static'               => true,
            'hide_menu'            => true,
            'contest'              => $contest,
            'scoreboard'           => $scoreboard,
            'showFlags'            => $this->dj->dbconfig_get('show_flags', true),
            'showAffiliationLogos' => $this->dj->dbconfig_get('show_affiliation_logos', false),
            'showAffiliations'     => $this->dj->dbconfig_get('show_affiliations', true),
            'showPending'          => $this->dj->dbconfig_get('show_pending', false),
            'showTeamSubmissions'  => $this->dj->dbconfig_get('show_teams_submissions', true),
            'scoreInSeconds'       => $this->dj->dbconfig_get('score_in_seconds', false),
            'maxWidth'             => $this->dj->dbconfig_get('team_column_width', 0),
        ];

        $output = $this->twig->render('public/scoreboard.html.twig', $data);

        # TODO: It would be nice to return a zip containing all relevant css/js/font files.
        echo $output;
        return 0;
    }
}
