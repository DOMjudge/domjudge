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
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use App\Service\SubmissionService;
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

class ImportTeamAffiliation extends TeamAffiliation {
	public function setAffilid($id){
		$this->affilid = $id;
	}
}
class ImportProblem extends Problem {
	public function setProbid($id){
		$this->probid = $id;
	}
}



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
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var ScoreboardService
     */
    protected $scoreboardService;

    /**
     * @var SubmissionService
     */
    protected $submissionService;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var bool
     */
    protected $debug;

    /**
     * @var LoggerInterface
     */
    protected $logger;


	// DATA FOR THIS CLASS
	protected $teams = [];
	protected $problems = [];
	protected $scorecache = [];
	protected $affiliations = [];


    /**
     * ScoreboardMergeCommand constructor.
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param EventLogService        $eventLogService
     * @param ScoreboardService      $scoreboardService
     * @param SubmissionService      $submissionService
     * @param TokenStorageInterface  $tokenStorage
     * @param bool                   $debug
     * @param string                 $domjudgeVersion
     * @param string|null            $name
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        EventLogService $eventLogService,
        ScoreboardService $scoreboardService,
        SubmissionService $submissionService,
        TokenStorageInterface $tokenStorage,
        bool $debug,
        string $domjudgeVersion,
        string $name = null
    ) {
        parent::__construct($name);
        $this->em                = $em;
        $this->dj                = $dj;
        $this->eventLogService   = $eventLogService;
        $this->scoreboardService = $scoreboardService;
        $this->submissionService = $submissionService;
        $this->tokenStorage      = $tokenStorage;
        $this->debug             = $debug;
        $this->domjudgeVersion   = $domjudgeVersion;
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
        // long runnning process.
        if (!$this->debug) {
            $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
        }

        // Set up logger
        $verbosityLevelMap = [
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
        ];
        $this->logger      = new ConsoleLogger($output, $verbosityLevelMap);

        #$this->logger->info(sprintf('Importing from local file %s', $input->getArgument('feed-url')));

		$paths = $input->getArgument('feed-url');

		foreach ($paths as $path){
			$client = HttpClient::create();
			$teams = $client->request('GET', $path.'/teams')->toArray();
			// Reading local files doesn't work in docker...
			//$teamsFile = fopen($path . 'teams.json', 'r');
			//$teamsText = fread($teamsFile, 1024*1024);
			//$teams = $this->dj->jsonDecode($teamsText);
			//fclose($teamsFile);

			dump($teams);

			$category = new TeamCategory();
			$category->setName("Participants");
			$category->setCategoryid(0);

			// Add teams.
			$teamIdMap = [];
			foreach ($teams as $team){
				$teamobj = new Team();
				$teamobj->setName($team['name']);
				$teamobj->setEnabled(true);
				if(!array_key_exists($team['affiliation'], $this->affiliations)){
					$affiliation = new ImportTeamAffiliation();
					$affiliation->setName($team['affiliation']);
					$affiliation->setAffilid(count($this->affiliations));
					$this->affiliations[$team['affiliation']] = $affiliation;
				}
				$teamobj->setAffiliation($this->affiliations[$team['affiliation']]);
				#$teamobj->setCategoryid(0);
				$teamobj->setCategory($category);
				$oldid = $team['id'];
				$newid = count($teamIdMap);
				$teamobj->setTeamid($newid);
				$this->teams[] = $teamobj;
				$teamIdMap[$oldid] = $newid;

			}

			$scoreboard = $client->request('GET', $path.'/scoreboard')->toArray();
			//$scoreboardFile = fopen($path . 'scoreboard.json', 'r');
			//$scoreboardText = fread($scoreboardFile, 1024*1024);
			//$scoreboard = $this->dj->jsonDecode($scoreboardText);
			//fclose($scoreboardFile);

			#dump($scoreboard);

			// Add scoreboard data
			foreach ($scoreboard['rows'] as $row){
				$team = $this->teams[$teamIdMap[$row['team_id']]];
				foreach ($row['problems'] as $problem){
					$scorecache = new ScoreCache();
					if(!array_key_exists($problem['label'], $this->problems)){
						$problemobj = new ImportProblem();
						$problemobj->setProbid($problem['label']);
						$problemobj->setName($problem['label']);
					}
					$this->problems[$problem['label']] = $problemobj;
					//$problemobj->setProbid($problem['label']);
					$scorecache->setProblem($problemobj);
					$scorecache->setTeam($team);
					if(array_key_exists('time', $problem)){
						$scorecache->setSolveTimePublic($problem['time']);
					}
					$scorecache->setSubmissionsPublic($problem['num_judged']);
					$scorecache->setIsCorrectPublic($problem['solved']);

					$this->scorecache[] = $scorecache;
				}
			}
		}

		// 'static' data:
		$contest = new Contest();

		$freezeData = new FreezeData($contest);

		$scoreboard = new Scoreboard(
			$contest,
			$this->teams,
			[$category],
			$this->problems,
			$this->scorecache,
			$freezeData,
			true,
			20,
			false
		);

        return 0;
    }
}
