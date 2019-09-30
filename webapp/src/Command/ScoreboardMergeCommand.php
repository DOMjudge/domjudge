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
use App\Service\ScoreboardService;
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
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ScoreboardService
     */
    protected $scoreboardService;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Environment
     */
    protected $twig;


    /**
     * ScoreboardMergeCommand constructor.
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param ScoreboardService      $scoreboardService
     * @param bool                   $debug
     * @param string|null            $name
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ScoreboardService $scoreboardService,
        bool $debug,
        Environment $twig,
        string $name = null
    ) {
        parent::__construct($name);
        $this->em                = $em;
        $this->dj                = $dj;
        $this->scoreboardService = $scoreboardService;
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
            ->setDescription('Merges scoreboards from multiple sites from API endpoints or raw json.')
            ->setHelp('TODO')
            ->addArgument(
                'contest-name',
                InputArgument::REQUIRED,
                'Title of the merged contest.'
            )
            ->addArgument(
                'feed-url',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'URL or directory location of the scoreboard to merge.' . PHP_EOL .
                'If an URL and it requires authentication, use username:password@ in the URL' . PHP_EOL .
                'URL should have the form https://<domain>/api/v4/contests/<contestid>/'
            );
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Disable SQL logging if we do not run explicitly in debug mode.
        // This would cause a serious memory leak otherwise since this is a
        // long running process.
        if (!$this->debug) {
            $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
        }

        // Set up logger
        $verbosityLevelMap = [
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
        ];
        $this->logger      = new ConsoleLogger($output, $verbosityLevelMap);

        // DATA
        $teams = [];
        $problems = [];
        $problemidmap = [];
        $scorecache = [];
        $affiliations = [];
        $nextTeamid = 0;

        $firstsolve = [];

        $category = new TeamCategory();
        $category->setName("Participants");
        $category->setCategoryid(0);

        $paths = $input->getArgument('feed-url');

        # Convert from flat list to list of (url, groups) pairs
        $sites = [];

        if(count($paths) % 2 != 0){
            echo "Provide an even number of arguments: all pairs of url and comma separated group ids.";
            return 1;
        }

        for($i = 0; $i < count($paths); $i += 2){
            $site = [];
            $site['path'] = $paths[$i];
            # Some simple validation to make sure we're actually parsing group ids.
            $groups_string = $paths[$i+1];
            if(!preg_match('/^\d+(,\d+)*$/', $groups_string)){
                echo 'Argument does not look like a comma separated list of group ids: ' . $groups_string . PHP_EOL;
                return 1;
            }
            $site['group_ids'] = array_map('intval', explode(',', $groups_string));
            $sites[] = $site;
        }

        $contest = Null;

        foreach ($sites as $site){
            $path = $site['path'];

            $client = HttpClient::create();
            $teamdata = $client->request('GET', $path.'/teams')->toArray();

            $teamIdMap = [];
            foreach ($teamdata as $team){

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

                $teamobj = new Team();
                $teamobj->setName($team['name']);
                $teamobj->setEnabled(true);
                if(!array_key_exists($team['affiliation'], $affiliations)){
                    $affiliation = new TeamAffiliation();
                    $affiliation->setName($team['affiliation']);
                    $affiliation->setAffilid(count($affiliations));
                    $affiliations[$team['affiliation']] = $affiliation;
                }
                $teamobj->setAffiliation($affiliations[$team['affiliation']]);
                $teamobj->setCategory($category);
                $oldid = $team['id'];
                $newid = $nextTeamid++;
                $teamobj->setTeamid($newid);
                $teams[] = $teamobj;
                $teamIdMap[$oldid] = $newid;
            }

            $scoreboarddata = $client->request('GET', $path.'/scoreboard')->toArray();

            if($contest === Null){
                $state = $scoreboarddata['state'];
                $contest = new Contest();
                $contest->setName($input->getArgument('contest-name'));
                $contest->setStarttimeString($state['started']);
                $contest->setEndtimeString($state['ended']);
                $contest->setFreezetimeString($state['ended']);
                $contest->setUnfreezetimeString($state['ended']);
                $contest->setFinalizetime($state['ended']);
                $contest->setDeactivatetimeString($state['ended']);
            }

            // Add scoreboard data
            foreach ($scoreboarddata['rows'] as $row){
                if(!array_key_exists($row['team_id'], $teamIdMap)){
                    continue;
                }
                $team = $teams[$teamIdMap[$row['team_id']]];
                foreach ($row['problems'] as $problem){
                    $label = $problem['label'];
                    if(!array_key_exists($problem['label'], $problemidmap)){
                        $id = count($problems);
                        $problemobj = new Problem();
                        $problemobj->setProbid($id);
                        $problemobj->setName($label);
                        $contestproblemobj = new ContestProblem();
                        $contestproblemobj->setProblem($problemobj);
                        $contestproblemobj->setShortName($label);
                        $problems[$id] = $contestproblemobj;
                        $problemidmap[$label] = $id;
                        $firstsolve[$label] = Null;
                    } else {
                        $id = $problemidmap[$label];
                    }
                    $scorecacheobj = new ScoreCache();
                    $scorecacheobj->setProblem($problems[$id]->getProblem());
                    $scorecacheobj->setTeam($team);
                    if(array_key_exists('time', $problem)){
                        $scorecacheobj->setSolveTimePublic($problem['time']*60);
                        $scorecacheobj->setSolveTimeRestricted($problem['time']*60);
                        if($firstsolve[$label] === Null or $problem['time']*60<$firstsolve[$label]){
                            $firstsolve[$label] = $problem['time']*60;
                        }
                    }
                    $scorecacheobj->setSubmissionsPublic($problem['num_judged']);
                    $scorecacheobj->setSubmissionsRestricted($problem['num_judged']);
                    $scorecacheobj->setIsCorrectPublic($problem['solved']);
                    $scorecacheobj->setIsCorrectRestricted($problem['solved']);
                    $scorecache[] = $scorecacheobj;
                }
            }
        }

        // Update first to solve fields.
        foreach ($scorecache as &$scorecacheobj){
            if($scorecacheobj->getSolveTimeRestricted() == $firstsolve[$scorecacheobj->getProblem()->getName()]){
                $scorecacheobj->setIsFirstToSolve(true);
            }
        }


        $freezeData = new FreezeData($contest);

        $scoreboard = new Scoreboard(
            $contest,
            $teams,
            [$category],
            $problems,
            $scorecache,
            $freezeData,
            /* jury = */ false,
            $this->dj->dbconfig_get('penalty_time', 20),
            false
        );

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

        echo $output;
        return 0;
    }
}
