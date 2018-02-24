<?php
namespace DOMJudgeBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;

use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Head;
use FOS\RestBundle\Controller\Annotations\Delete;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Event;
use DOMJudgeBundle\Utils\Utils;

/**
 * @Route("/api", defaults={ "_format" = "json" })
 */
class APIController extends FOSRestController {

	public $apiVersion = 4;

	/**
	 * @Get("/")
	 */
	public function getCurrentActiveContestAction() {
		$contests = $this->getContestsAction();
		if (count($contests) == 0) {
			return null;
		} else {
			return $contests[0];
		}
	}

	/**
	 * @Patch("/contests/{externalid}")
	 * @Security("has_role('ROLE_ADMIN')")
	 */
	public function changeStartTimeAction(Request $request, Contest $contest) {
		$args = $request->request->all();
		$response = NULL;
		$now = Utils::now();
		if ( !isset($args['id']) ) {
			$response = new Response('Missing "id" in request.', 400);
		} else if ( !array_key_exists('start_time', $args) ) {
			$response = new Response('Missing "start_time" in request.', 400);
		} else if ( $args['id'] != $contest->getExternalid() ) {
			$response = new Response('Invalid "id" in request.', 400);
		} else if ( !isset($args['force']) && $contest->getStarttime() != NULL && $contest->getStarttimeEnabled() && $contest->getStarttime() < $now + 30 ) {
			$response = new Response('Current contest already started or about to start.', 403);
		} else if ( $args['start_time'] === NULL ) {
			$em = $this->getDoctrine()->getManager();
			$em->persist($contest);
			$contest->setStarttimeEnabled(FALSE);
			$response = new Response('Contest paused :-/.', 200);
			$em->flush();
		} else {
			$em = $this->getDoctrine()->getManager();
			$date = date_create($args['start_time']);
			if ( $date === FALSE) {
				$response = new Response('Invalid "start_time" in request.', 400);
			} else {
				$new_start_time = $date->getTimestamp();
				if ( !isset($args['force']) && $new_start_time < $now + 30 ) {
					$response = new Response('New start_time not far enough in the future.', 403);
				} else {
					$em->persist($contest);
					$newStartTimeString = date('Y-m-d H:i:s e', $new_start_time);
					$contest->setStarttimeEnabled(TRUE);
					$contest->setStarttime($new_start_time);
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
	public function getContestsAction() {
		$request = Request::createFromGlobals();
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

		$result = [];
		foreach ($data as $contest) {
			if ($contest->isActive()) {
				$result[] = $contest->serializeForAPI($penalty_time);
			}
		}

		return $result;
	}

	/**
	 * @Get("/contests/{externalid}")
	 */
	public function getSingleContestAction(Contest $contest) {
		$request = Request::createFromGlobals();
		$strict = false;
		if ($request->query->has('strict')) {
			$strict = $request->query->getBoolean('strict');
		}
		$isAdmin = $this->isGranted('ROLE_ADMIN');
		if (($isAdmin && $contest->getEnabled())
			|| (!$isAdmin && $contest->isActive())) {
			$penalty_time = $this->get('domjudge.domjudge')->dbconfig_get('penalty_time', 20);
			return $contest->serializeForAPI($penalty_time, $strict);
		} else {
			return NULL;
		}
	}

	/**
	 * @Get("/contests/{externalid}/contest-yaml")
	 */
	public function getContestYaml(Contest $contest) {
		$penalty_time = $this->get('domjudge.domjudge')->dbconfig_get('penalty_time', 20);
		$response = new StreamedResponse();
		$response->setCallback(function () use ($contest, $penalty_time) {
				echo "name:                     " . $contest->getName() . "\n";
				echo "short-name:               " . $contest->getExternalid() . "\n";
				echo "start-time:               " . Utils::absTime($contest->getStarttime(), TRUE) . "\n";
				echo "duration:                 " . Utils::relTime($contest->getEndtime() - $contest->getStarttime(), TRUE) . "\n";
				echo "scoreboard-freeze-length: " . Utils::relTime($contest->getEndtime() - $contest->getFreezetime(), TRUE) . "\n";
				echo "penalty-time:             " . $penalty_time . "\n";
		});
		$response->headers->set('Content-Type', 'text-plain');
		$response->headers->set('Content-Disposition', 'attachment; filename="contest.yaml"');
		$response->headers->set('Content-Transfer-Encoding', 'binary');
		$response->headers->set('Connection', 'Keep-Alive');
		$response->headers->set('Accept-Ranges','bytes');

		return $response;
	}

	/**
	 * @Get("/contests/{externalid}/state")
	 */
	public function getContestState(Contest $contest) {
		$isAdmin = $this->isGranted('ROLE_ADMIN');
		if (($isAdmin && $contest->getEnabled())
			|| (!$isAdmin && $contest->isActive())) {
			$time_or_null = function($time, $extra_cond = true) {
				if ( !$extra_cond || $time===null || time()<$time ) return null;
				return Utils::absTime($time);
			};
			$result = [];
			$result['started']   = $time_or_null($contest->getStarttime());
			$result['ended']     = $time_or_null($contest->getEndtime(), $result['started']!==null);
			$result['frozen']    = $time_or_null($contest->getFreezetime(), $result['started']!==null);
			$result['thawed']    = $time_or_null($contest->getUnfreezetime(), $result['frozen']!==null);
			if ( $isAdmin ) {
				$result['finalized'] = $time_or_null($contest->getFinalizetime(), $result['ended']!==null);
			} else {
				if ( $result['frozen'] && !$result['thawed'] ) {
					$result['finalized'] = null;
				} else {
					$result['finalized'] = $time_or_null(max($contest->getFinalizetime(),
					                                         $contest->getUnfreezetime()),
					                                     $result['ended']!==null &&
					                                     $contest->getFinalizetime()!==null);
				}
			}

			return $result;
		} else {
			return NULL;
		}
	}

	/**
	 * @Get("/v4/contests/{externalid}/state")
	 */
	public function getContestStateV4(Contest $contest) {
		return $this->getContestState($contest);
	}

	/**
	 * @Get("/contests/{externalid}/event-feed")
	 */
	public function getEventFeed(Request $request, Contest $contest) {
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
		if ($request->query->has('id')) {
			$event = $em->getRepository(Event::class)->findOneBy(
				array(
					'eventid' => $request->query->getInt('id'),
					'cid'     => $contest['id'],
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
			$strict = false;
			if ($request->query->has('strict')) {
				$strict = $request->query->getBoolean('strict');
			}
			$stream = true;
			if ($request->query->has('stream')) {
				$stream = $request->query->getBoolean('stream');
			}
			$isAdmin = $this->isGranted('ROLE_ADMIN');
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
				if ( !$isAdmin ) {
					$qb = $qb
						->andWhere('e.endpointtype NOT IN (:types)')
						->setParameter(':types', ['judgements', 'runs']);
				}

				$q = $qb->getQuery();

				$events = $q->getResult();
				foreach ($events as $event) {
					// FIXME: use the dj_* wrapper as in lib/lib.wrapper.php.
					$data = json_decode(stream_get_contents($event['content']), TRUE);
					if ( !$isAdmin && $event['endpointtype'] == 'submissions' ) {
						unset($data['entry_point']);
					}
					$result = array(
						'id'        => (string)$event['eventid'],
						'type'      => (string)$event['endpointtype'],
						'op'        => (string)$event['action'],
						'data'      => $data,
					);
					if ( !$strict ) $result['time'] = Utils::absTime($event['eventtime']);
					echo json_encode($result) . "\n";
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
