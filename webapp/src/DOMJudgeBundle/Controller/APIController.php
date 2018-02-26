<?php
namespace DOMJudgeBundle\Controller;

use DOMJudgeBundle\Entity\Judging;
use DOMJudgeBundle\Entity\Language;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Entity\SubmissionFile;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Entity\TeamAffiliation;
use DOMJudgeBundle\Entity\TeamCategory;
use FOS\RestBundle\Controller\FOSRestController;

use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Head;
use FOS\RestBundle\Controller\Annotations\Delete;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Event;
use DOMJudgeBundle\Utils\Utils;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/api/v5", defaults={ "_format" = "json" })
 */
class APIController extends FOSRestController {

	public $apiVersion = 5;

	/**
	 * @Patch("/contests/{id}")
	 * @Security("has_role('ROLE_ADMIN')")
	 */
	public function changeStartTimeAction(Request $request, Contest $contest) {
		$args = $request->request->all();
		$response = NULL;
		if ( !isset($args['id']) ) {
			$response = new Response('Missing "id" in request.', 400);
		} else if ( !isset($args['start_time']) ) {
			$response = new Response('Missing "start_time" in request.', 400);
		} else if ( $args['id'] != $contest->getCid() ) {
			$response = new Response('Invalid "id" in request.', 400);
		} else {
			$em = $this->getDoctrine()->getManager();
			$date = date_create($args['start_time']);
			if ( $date === FALSE) {
				$response = new Response('Invalid "start_time" in request.', 400);
			} else {
				$new_start_time = $date->getTimestamp();
				$now = Utils::now();
				if ( $new_start_time < $now + 30 ) {
					$response = new Response('New start_time not far enough in the future.', 403);
				} else if ( FALSE && $contest->getStarttime() != NULL && $contest->getStarttime() < $now + 30 ) {
					$response = new Response('Current contest already started or about to start.', 403);
				} else {
					$em->persist($contest);
					$newStartTimeString = date('Y-m-d H:i:s e', $new_start_time);
					$contest->setStarttimeString($newStartTimeString);
					$response = new Response('Contest start time changed to ' . $newStartTimeString, 200);
					$em->flush();
				}
			}
		}
		return $response;
	}

	/**
	 * @Get("/version")
	 */
	public function getVersionAction() {
		$data = ['api_version' => $this->apiVersion];
		return $data;
	}

	/**
	 * @Get("/info")
	 */
	public function getInfoAction() {
		$data = [
			'api_version' => $this->apiVersion,
			'domjudge_version' => $this->getParameter('domjudge.version'),
		];
		return $data;
	}

	/**
	 * @Get("/contests")
	 */
	public function getContestsAction(Request $request) {
		$strict = false;
		if ($request->query->has('strict')) {
			$strict = $request->query->getBoolean('strict');
		}
		$em = $this->getDoctrine()->getManager();
		$data = $em->getRepository(Contest::class)->findBy(
			array(
				'enabled' => TRUE,
				'public' => TRUE,
			)
		);
		$penalty_time = $this->get('domjudge.domjudge')->dbconfig_get('penalty_time', 20);
		$use_ext_ids = $this->getParameter('domjudge.useexternalids');

		$result = [];
		foreach ($data as $contest) {
			if ($contest->isActive()) {
				$result[] = $contest->serializeForAPI($penalty_time, $use_ext_ids);
			}
		}

		return $result;
	}

	/**
	 * @Get("/contests/{cid}")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 */
	public function getSingleContestAction(Request $request, Contest $contest) {
		$strict = false;
		if ($request->query->has('strict')) {
			$strict = $request->query->getBoolean('strict');
		}
		$isJury = $this->isGranted('ROLE_JURY');
		if (($isJury && $contest->getEnabled())
			|| (!$isJury && $contest->isActive())) {
			$penalty_time = $this->get('domjudge.domjudge')->dbconfig_get('penalty_time', 20);
			$use_ext_ids = $this->getParameter('domjudge.useexternalids');
			return $contest->serializeForAPI($penalty_time, $use_ext_ids, $strict);
		} else {
			return NULL;
		}
	}

	/**
	 * @Get("/contests/{cid}/state")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 */
	public function getContestStateAction(Contest $contest) {
		$time_or_null = function($time, $extra_cond = true) {
			if ( !$extra_cond || $time===null || time()<$time ) return null;
			return Utils::absTime($time);
		};
		$result = [];
		$result['started']   = $time_or_null($contest->getStarttime());
		$result['ended']     = $time_or_null($contest->getEndtime(), $result['started']!==null);
		$result['frozen']    = $time_or_null($contest->getFreezetime(), $result['started']!==null);
		$result['thawed']    = $time_or_null($contest->getUnfreezetime(), $result['frozen']!==null);
		// TODO: do not set this for public access (first needs public role)
		// TODO: use real finalized time when we have it (e.g. in ICPC-live branch)
		$result['finalized'] = $time_or_null($contest->getUnfreezetime(), ($result['ended']!==null && $result['thawed']!==null));

		return $result;
	}

	/**
	 * @Get("/contests/{cid}/judgement-types")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 */
	public function getJudgementTypesAction(Contest $contest) {
		$etcDir = realpath($this->getParameter('kernel.root_dir') . '/../../etc/');
		$VERDICTS = [];
		require_once($etcDir . '/common-config.php');
		$result = [];

		foreach ($VERDICTS as $name => $label) {
			$penalty = true;
			$solved = false;
			if ($name == 'correct') {
				$penalty = false;
				$solved = true;
			}
			if ($name == 'compiler-error') {
				$penalty = (bool)$this->get('domjudge.domjudge')->dbconfig_get('compile_penalty', false);
			}
			$result[] = [
				'id' => $label,
				'name' => str_replace('-', ' ', $name),
				'penalty' => $penalty,
				'solved' => $solved,
			];
		}

		return $result;
	}

	/**
	 * @Get("/contests/{cid}/judgement-types/{id}")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 */
	public function getJudgementTypeAction(Contest $contest, $id) {
		$judgementTypes = $this->getJudgementTypesAction($contest);
		foreach ($judgementTypes as $judgementType) {
			if ($judgementType['id'] === $id) {
				return $judgementType;
			}
		}

		throw new NotFoundHttpException(sprintf('Judgement type %s not found', $id));
	}

	/**
	 * @Get("/contests/{cid}/languages")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 */
	public function getLanguagesAction(Request $request, Contest $contest) {
		$languages = $this->getDoctrine()->getRepository(Language::class)->findBy(['allow_submit' => true]);

		return array_map(function(Language $language) use ($request) {
			return $language->serializeForAPI($this->getParameter('domjudge.useexternalids'), $request->query->getBoolean('strict', true));
		}, $languages);
	}

	/**
	 * @Get("/contests/{cid}/languages/{id}")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 */
	public function getLanguageAction(Request $request, Contest $contest, $id) {
		if ($language = $this->getDoctrine()->getRepository(Language::class)->findOneBy(['langid' => $id, 'allow_submit' => true])) {
			return $language->serializeForAPI($this->getParameter('domjudge.useexternalids'), $request->query->getBoolean('strict', true));
		} else {
			throw new NotFoundHttpException(sprintf('Language %s not found', $id));
		}
	}

	/**
	 * @Get("/contests/{cid}/problems")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 */
	public function getProblemsAction(Request $request, Contest $contest) {
		// TODO: add security check for public/admin. I can't seem to get checkrole() working
		$problems = $this->getDoctrine()->getRepository(Problem::class)->findAllForContest($contest);

		$ordinal = 0;
		return array_map(function($problemData) use (&$ordinal, $request) {
			/** @var Problem $problem */
			$problem = $problemData[0];
			$num_testcases = $problemData['num_testcases'];
			return $problem->serializeForAPI($num_testcases, $this->getParameter('domjudge.useexternalids'), $request->query->getBoolean('strict', true)) + ['ordinal' => $ordinal++];
		}, $problems);
	}

	/**
	 * @Get("/contests/{cid}/problems/{id}")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 */
	public function getProblemAction(Request $request, Contest $contest, $id) {
		// TODO: add security check for public/admin. I can't seem to get checkrole() working
		$problems = $this->getDoctrine()->getRepository(Problem::class)->findAllForContest($contest);

		/**
		 * @var Problem $problem
		 */
		foreach ($problems as $idx => $problemData) {
			/** @var Problem $problem */
			$problem = $problemData[0];
			$num_testcases = $problemData['num_testcases'];
			if (($this->getParameter('domjudge.useexternalids') && $problem->getExternalid() == $id) || (!$this->getParameter('domjudge.useexternalids')) && $problem->getProbid() == $id) {
				return $problem->serializeForAPI($num_testcases, $this->getParameter('domjudge.useexternalids'), $request->query->getBoolean('strict', true)) + ['ordinal' => $idx];
			}
		}

		throw new NotFoundHttpException(sprintf('Problem %s not found', $id));
	}

	/**
	 * @Get("/contests/{cid}/groups")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 */
	public function getGroupsAction(Request $request, Contest $contest) {
		$groups = $this->getDoctrine()->getRepository(TeamCategory::class)->findAll();

		return array_map(function(TeamCategory $teamCategory) use ($request) {
			return $teamCategory->serializeForAPI($request->query->getBoolean('strict', true));
		}, $groups);
	}

	/**
	 * @Get("/contests/{cid}/groups/{id}")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 * @ParamConverter("teamCategory", converter="domjudge.api_entity_param_converter")
	 */
	public function getGroupAction(Request $request, Contest $contest, TeamCategory $teamCategory) {
		return $teamCategory->serializeForAPI($request->query->getBoolean('strict', true));
	}

	/**
	 * @Get("/contests/{cid}/organizations")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 */
	public function getOrganizationsAction(Request $request, Contest $contest) {
		$groups = $this->getDoctrine()->getRepository(TeamAffiliation::class)->findAll();

		return array_map(function(TeamAffiliation $teamAffiliation) use ($request) {
			return $teamAffiliation->serializeForAPI($this->getParameter('domjudge.useexternalids'), $request->query->getBoolean('strict', true));
		}, $groups);
	}

	/**
	 * @Get("/contests/{cid}/organizations/{id}")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 * @ParamConverter("teamAffiliation", converter="domjudge.api_entity_param_converter")
	 */
	public function getOrganizationAction(Request $request, Contest $contest, TeamAffiliation $teamAffiliation) {
		return $teamAffiliation->serializeForAPI($this->getParameter('domjudge.useexternalids'), $request->query->getBoolean('strict', true));
	}

	/**
	 * @Get("/contests/{cid}/teams")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 */
	public function getTeamsAction(Request $request, Contest $contest) {
		$teams = $this->getDoctrine()->getRepository(Team::class)->findAllForContest($contest, !$this->get('security.authorization_checker')->isGranted('ROLE_JURY'));

		return array_map(function(Team $team) use ($request) {
			return $team->serializeForAPI($this->getParameter('domjudge.useexternalids'), $request->query->getBoolean('strict', true));
		}, $teams);
	}

	/**
	 * @Get("/contests/{cid}/teams/{id}")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 */
	public function getTeamAction(Request $request, Contest $contest, $id) {
		$useExternalIds = $this->getParameter('domjudge.useexternalids');
		$team = $this->getDoctrine()->getRepository(Team::class)->findForContest($contest, $id, $useExternalIds, !$this->get('security.authorization_checker')->isGranted('ROLE_JURY'));
		return $team->serializeForAPI($useExternalIds, $request->query->getBoolean('strict', true));
	}

	/**
	 * @Get("/contests/{cid}/submissions")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 */
	public function getSubmissionsAction(Request $request, Contest $contest) {
		$submissions = $this->getDoctrine()->getRepository(Submission::class)->findBy([
			'valid' => 1,
			'cid' => $contest->getCid()
		]);

		return array_map(function(Submission $submission) use ($request) {
			return $submission->serializeForAPI($this->getParameter('domjudge.useexternalids'));
		}, $submissions);
	}

	/**
	 * @Get("/contests/{cid}/submissions/{id}")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 */
	public function getSubmissionAction(Request $request, Contest $contest, $id) {
		$submission = $this->getDoctrine()->getRepository(Submission::class)->findOneBy([
			'submitid' => $id,
			'valid' => 1,
			'cid' => $contest->getCid()
		]);

		if (!$submission) {
			throw new NotFoundHttpException(sprintf('Submission %s not found', $id));
		}

		return $submission->serializeForAPI($this->getParameter('domjudge.useexternalids'));
	}

	/**
	 * @Get("/contests/{cid}/submissions/{id}/files")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 */
	public function getSubmissionFilesAction(Request $request, Contest $contest, $id)
	{
		$submission = $this->getDoctrine()->getRepository(Submission::class)->findOneBy([
			'submitid' => $id,
			'valid' => 1,
			'cid' => $contest->getCid()
		]);

		if (!$submission) {
			throw new NotFoundHttpException(sprintf('Submission %s not found', $id));
		}

		$files = $submission->getFiles();

		$zip = new \ZipArchive;
		if ( !($tmpfname = tempnam($this->getParameter('domjudge.tmpdir'), "submission_file-")) ) {
			error("Could not create temporary file.");
		}

		$res = $zip->open($tmpfname, \ZipArchive::OVERWRITE);
		if ( $res !== TRUE ) {
			error("Could not create temporary zip file.");
		}
		foreach ($files as $file) {
			$zip->addFromString($file->getFilename(), stream_get_contents($file->getSourcecode()));
		}
		$zip->close();

		$filename = 's' . $submission->getSubmitid() . '.zip';

		$response = new StreamedResponse();
		$response->setCallback(function () use ($tmpfname) {
			$fp = fopen($tmpfname, 'rb');
			fpassthru($fp);
			unlink($tmpfname);
		});
		$response->headers->set('Content-Type', 'application/zip');
		$response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
		$response->headers->set('Content-Length', filesize($tmpfname));
		$response->headers->set('Content-Transfer-Encoding', 'binary');
		$response->headers->set('Connection', 'Keep-Alive');
		$response->headers->set('Accept-Ranges','bytes');

		return $response;
	}

	/**
	 * @Get("/contests/{cid}/judgements")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 * @Security("has_role('ROLE_JURY')")
	 * TODO: allow for public access for non-frozen judgings
	 */
	public function getJudgementsAction(Request $request, Contest $contest) {
		$judgings = $this->getDoctrine()->getRepository(Judging::class)->findAllForContest($contest);

		$etcDir = realpath($this->getParameter('kernel.root_dir') . '/../../etc/');
		$VERDICTS = [];
		require_once($etcDir . '/common-config.php');

		return array_map(function($data) use ($request, $VERDICTS) {
			/** @var Judging $judging */
			$judging = $data[0];
			return $judging->serializeForAPI($data['maxruntime'], $VERDICTS);
		}, $judgings);
	}

	/**
	 * @Get("/contests/{cid}/judgements/{id}")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 * @Security("has_role('ROLE_JURY')")
	 * TODO: allow for public access for non-frozen judgings
	 */
	public function getJudgementAction(Request $request, Contest $contest, $id) {
		$judgingData = $this->getDoctrine()->getRepository(Judging::class)->findOneForContest($contest, $id);

		$etcDir = realpath($this->getParameter('kernel.root_dir') . '/../../etc/');
		$VERDICTS = [];
		require_once($etcDir . '/common-config.php');

		return $judgingData[0]->serializeForAPI($judgingData['maxruntime'], $VERDICTS);
	}

	/**
	 * @Get("/contests/{cid}/event-feed")
	 * @ParamConverter("contest", converter="domjudge.api_entity_param_converter")
	 */
	public function getEventFeedAction(Request $request, Contest $contest) {
		// Make sure this script doesn't hit the PHP maximum execution timeout.
		set_time_limit(0);
		$em = $this->getDoctrine()->getManager();
		if ($request->query->has('since_id')) {
			$since_id = $request->query->getInt('since_id');
			$event = $em->getRepository(Event::class)->findOneBy(
				array(
					'eventid' => $since_id,
					'cid'     => $contest->getCid(),
				)
			);
			if ( $event===NULL ) {
				return new Response('Invalid parameter "since_id" requested.', 400);
			}
		} else {
			$since_id = -1;
		}
		$response = new StreamedResponse();
		$response->headers->set('X-Accel-Buffering', 'no');
		$response->setCallback(function () use ($em, $contest, $request, $since_id) {
			$lastUpdate = 0;
			$lastIdSent = $since_id;
			$typeFilter = false;
			if ($request->query->has('types')) {
				$typeFilter = explode(',', $request->query->get('types'));
			}
			$strict = false;
			if ($request->query->has('strict')) {
				$strict = $request->query->getBoolean('strict');
			}
			$stream = true;
			if ($request->query->has('stream')) {
				$stream = $request->query->getBoolean('stream');
			}
			$isJury = $this->isGranted('ROLE_JURY');
			while (TRUE) {
				$qb = $em->createQueryBuilder()
					->from('DOMJudgeBundle:Event', 'e')
					->select('e.eventid,e.eventtime,e.endpointtype,e.endpointid,e.datatype,e.dataid,e.action,e.content')
					->where('e.eventid > :lastIdSent')
					->setParameter('lastIdSent', $lastIdSent)
					->andWhere('e.cid = :cid')
					->setParameter('cid', $contest->getCid())
					->orderBy('e.eventid', 'ASC');

				if ($typeFilter !== false) {
					$qb = $qb
						->andWhere('e.endpointtype IN (:types)')
						->setParameter(':types', $typeFilter);
				}
				if ( !$isJury ) {
					$qb = $qb
						->andWhere('e.endpointtype NOT IN (:types)')
						->setParameter(':types', ['judgements', 'runs']);
				}

				$q = $qb->getQuery();

				$events = $q->getResult();
				foreach ($events as $event) {
					// FIXME: use the dj_* wrapper as in lib/lib.wrapper.php.
					$data = json_decode(stream_get_contents($event['content']), TRUE);
					if ( !$isJury && $event['endpointtype'] == 'submissions' ) {
						unset($data['entry_point']);
					}
					$result = array(
						'id'        => (string)$event['eventid'],
						'type'      => (string)$event['endpointtype'],
						'op'        => (string)$event['action'],
						'data'      => $data,
					);
					if ( !$strict ) $result['time'] = Utils::absTime($event['eventtime']);
					echo json_encode($result, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES) . "\n";
					ob_flush();
					flush();
					$lastUpdate = time();
					$lastIdSent = $event['eventid'];
				}

				if ( count($events) == 0 ) {
					if ( !$stream ) break;
					// No new events, check if it's time for a keep alive.
					$now = time();
					if ( $lastUpdate + 60 < $now ) {
						# Send keep alive every 60s. Guarantee according to spec is 120s.
						echo "\n";
						ob_flush();
						flush();
						$lastUpdate = $now;
					}
					# Sleep for little while before checking for new events.
					usleep(50000);
				}
			}
		});
		return $response;
	}
}
