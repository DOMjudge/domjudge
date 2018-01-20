<?php
namespace DOMJudgeBundle\Controller;

use DOMJudgeBundle\Entity\Language;
use DOMJudgeBundle\Entity\Problem;
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
 * @Route("/api", defaults={ "_format" = "json" })
 */
class APIController extends FOSRestController {

	public $apiVersion = 5;

	/**
	 * @Patch("/")
	 * @Security("has_role('ROLE_ADMIN')")
	 */
	public function changeStartTimeAction(Request $request) {
		$contest = $this->getCurrentActiveContestAction();
		if ($contest === NULL) {
			return NULL;
		}
		$args = $request->request->all();
		$response = NULL;
		if ( !isset($args['id']) ) {
			$response = new Response('Missing "id" in request.', 400);
		} else if ( !isset($args['start_time']) ) {
			$response = new Response('Missing "start_time" in request.', 400);
		} else if ( $args['id'] != $contest['id'] ) {
			$response = new Response('Invalid "id" in request.', 400);
		} else {
			$em = $this->getDoctrine()->getManager();
			$contestObject = $em->getRepository(Contest::class)->findOneBy(
				array(
					'cid' => $args['id'],
				)
			);
			$date = date_create($args['start_time']);
			if ( $date === FALSE) {
				$response = new Response('Invalid "start_time" in request.', 400);
			} else {
				$new_start_time = $date->getTimestamp();
				$now = Utils::now();
				if ( $new_start_time < $now + 30 ) {
					$response = new Response('New start_time not far enough in the future.', 403);
				} else if ( FALSE && $contestObject->getStarttime() != NULL && $contestObject->getStarttime() < $now + 30 ) {
					$response = new Response('Current contest already started or about to start.', 403);
				} else {
					$em->persist($contestObject);
					$newStartTimeString = date('Y-m-d H:i:s e', $new_start_time);
					$contestObject->setStarttimeString($newStartTimeString);
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
	public function getContestsAction() {
		$em = $this->getDoctrine()->getManager();
		$data = $em->getRepository(Contest::class)->findBy(
			array(
				'enabled' => TRUE,
				'public' => TRUE,
			)
		);

		return Contest::filterActiveContests($data, $this->get('domjudge.domjudge')->dbconfig_get('penalty_time', 20), $this->getParameter('domjudge.useexternalids'));
	}

	/**
	 * @Get("/contests/{id}")
	 */
	public function getSingleContestAction(Contest $contest) {
		if ($contest->isActive()) {
			return $contest->serializeForAPI($this->get('domjudge.domjudge')->dbconfig_get('penalty_time', 20), $this->getParameter('domjudge.useexternalids'));
		} else {
			throw new NotFoundHttpException(sprintf('Contest %s not found', $this->getParameter('domjudge.useexternalids') ? $contest->getExternalid() : $contest->getCid()));
		}
	}

	/**
	 * @Get("/contests/{id}/state")
	 */
	public function getContestStateAction(Contest $contest) {
		if ($contest->isActive()) {
			$result = [];
			$result['started'] = $contest->getStarttime() <= time() ? Utils::absTime($contest->getStarttime()) : null;
			$result['ended'] = ($result['started'] !== null && $contest->getEndtime() <= time()) ? Utils::absTime($contest->getEndtime()) : null;
			$result['frozen'] = ($result['started'] !== null && $contest->getFreezetime() <= time()) ? Utils::absTime($contest->getFreezetime()) : null;
			$result['thawed'] = ($result['frozen'] !== null && $contest->getUnfreezetime() <= time()) ? Utils::absTime($contest->getUnfreezetime()) : null;
			// TODO: do not set this for public access (first needs public role)
			$result['finalized'] = ($result['ended'] !== null && $contest->getEndtime() <= time()) ? Utils::absTime($contest->getEndtime()) : null;

			return $result;
		} else {
			throw new NotFoundHttpException(sprintf('Contest %s not found', $this->getParameter('domjudge.useexternalids') ? $contest->getExternalid() : $contest->getCid()));
		}
	}

	/**
	 * @Get("/contests/{cid}/judgement-types")
	 */
	public function getJudgementTypesAction() {
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
				$penalty = $this->get('domjudge.domjudge')->dbconfig_get('compile_penalty', false);
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
	 */
	public function getJudgementTypeAction($id) {
		$judgementTypes = $this->getJudgementTypes();
		foreach ($judgementTypes as $judgementType) {
			if ($judgementType['id'] === $id) {
				return $judgementType;
			}
		}

		throw new NotFoundHttpException(sprintf('Judgement type %s not found', $id));
	}

	/**
	 * @Get("/contests/{cid}/languages")
	 */
	public function getLanguagesAction(Request $request) {
		$languages = $this->getDoctrine()->getRepository(Language::class)->findBy(['allow_submit' => true]);

		return array_map(function(Language $language) use ($request) {
			return $language->serializeForAPI($this->getParameter('domjudge.useexternalids'), $request->query->getBoolean('strict', true));
		}, $languages);
	}

	/**
	 * @Get("/contests/{cid}/languages/{id}")
	 */
	public function getLanguageAction(Request $request, $id) {
		if ($language = $this->getDoctrine()->getRepository(Language::class)->findOneBy(['langid' => $id, 'allow_submit' => true])) {
			return $language->serializeForAPI($this->getParameter('domjudge.useexternalids'), $request->query->getBoolean('strict', true));
		} else {
			throw new NotFoundHttpException(sprintf('Language %s not found', $id));
		}
	}

	/**
	 * @Get("/contests/{cid}/problems")
	 */
	public function getProblemsAction(Request $request, Contest $contest) {
		// TODO: add security check for public/admin. I can't seem to get checkrole() working
		$problems = $this->getDoctrine()->getRepository(Problem::class)->findAllForContest($contest);

		$ordinal = 0;
		return array_map(function(Problem $problem) use (&$ordinal, $request) {
			return $problem->serializeForAPI($this->getParameter('domjudge.useexternalids'), $request->query->getBoolean('strict', true)) + ['ordinal' => $ordinal++];
		}, $problems);
	}

	/**
	 * @Get("/contests/{cid}/problems/{id}")
	 */
	public function getProblemAction(Request $request, Contest $contest, $id) {
		// TODO: add security check for public/admin. I can't seem to get checkrole() working
		$problems = $this->getDoctrine()->getRepository(Problem::class)->findAllForContest($contest);

		/**
		 * @var Problem $problem
		 */
		foreach ($problems as $idx => $problem) {
			if (($this->getParameter('domjudge.useexternalids') && $problem->getExternalid() == $id) || (!$this->getParameter('domjudge.useexternalids')) && $problem->getProbid() == $id) {
				return $problem->serializeForAPI($this->getParameter('domjudge.useexternalids'), $request->query->getBoolean('strict', true)) + ['ordinal' => $idx];
			}
		}

		throw new NotFoundHttpException(sprintf('Problem %s not found', $id));
	}

	/**
	 * @Get("/contests/{cid}/event-feed")
	 */
	public function getEventFeedAction(Request $request, Contest $contest) {
		// Make sure this script doesn't hit the PHP maximum execution timeout.
		set_time_limit(0);
		$em = $this->getDoctrine()->getManager();
		if ($request->query->has('id')) {
			$event = $em->getRepository(Event::class)->findOneBy(
				array(
					'eventid' => $request->query->getInt('id'),
					'cid'     => $contest->getCid(),
				)
			);
			if ( $event===NULL ) {
				return new Response('Invalid parameter "id" requested.', 400);
			}
		}
		$response = new StreamedResponse();
		$response->headers->set('X-Accel-Buffering', 'no');
		$response->setCallback(function () use ($em, $contest, $request) {
			$lastUpdate = 0;
			$lastIdSent = -1;
			if ($request->query->has('since_id')) {
				$lastIdSent = $request->query->getInt('since_id');
			}
			$typeFilter = false;
			if ($request->query->has('types')) {
				$typeFilter = explode(',', $request->query->get('types'));
			}
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

				$q = $qb->getQuery();

				$events = $q->getResult();
				foreach ($events as $event) {
					// FIXME: use the dj_* wrapper as in lib/lib.wrapper.php.
					$data = json_decode(stream_get_contents($event['content']), TRUE);
					echo json_encode(array(
						'id'        => (string)$event['eventid'],
						'type'      => (string)$event['endpointtype'],
						'op'        => (string)$event['action'],
						'time'      => Utils::absTime($event['eventtime']),
						'data'      => $data,
					), JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES) . "\n";
					ob_flush();
					flush();
					$lastUpdate = time();
					$lastIdSent = $event['eventid'];
				}

				if ( count($events) == 0 ) {
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
