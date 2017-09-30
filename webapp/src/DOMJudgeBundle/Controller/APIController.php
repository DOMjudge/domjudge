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
use DOMJudgeBundle\Utils\Utils;

/**
 * @Route("/api", defaults={ "_format" = "json" })
 */
class APIController extends FOSRestController {

	public $apiVersion = 4;

	/**
	 * @Get("/")
	 */
	public function getCurrentActiveContest() {
		$contests = $this->getContestsAction();
		if (count($contests) == 0) {
			return null;
		} else {
			return $contests[0];
		}
	}

	/**
	 * @Patch("/")
	 * @Security("has_role('ROLE_ADMIN')")
	 */
	public function changeStartTime(Request $request) {
		$contest = $this->getCurrentActiveContest();
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
			$date = \DateTime::createFromFormat(\DateTime::ATOM, $args['start_time']);
			if ( $date === FALSE) {
				$response = new Response('Invalid "start_time" in request.', 400);
			} else {
				$new_start_time = $date->getTimestamp();
				$now = microtime(TRUE);
				if ( $new_start_time < $now + 30 ) {
					$response = new Response('New start_time not far in enough in future.', 403);
				} else if ( FALSE && $contestObject->getStarttime() != NULL && $contestObject->getStarttime() < $now + 30 ) {
					$response = new Response('Current contest already started or about to start.', 403);
				} else {
					$em->persist($contestObject);
					$newStartTimeString = date('Y-m-d H:i:s e', $new_start_time);
					$contestObject->setStarttimeString($newStartTimeString);
					$contestObject->setStarttime($new_start_time);
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

		return Contest::filterActiveContests($data);
	}

	/**
	 * @Get("/contests/{cid}")
	 */
	public function getSingleContestAction(Contest $cid) {
		if ($cid->isActive()) {
			return $cid->serializeForAPI();
		} else {
			return NULL;
		}
	}

	/**
	 * @Get("/event-feed")
	 */
	public function getEventFeed() {
		$em = $this->getDoctrine()->getManager();
		$response = new StreamedResponse();
		$response->headers->set('X-Accel-Buffering', 'no');
		$response->setCallback(function () use ($em) {
			$lastUpdate = 0;
			$lastIdSent = -1;
			while (TRUE) {
				// FIXME: filter for contest
				$q = $em->createQueryBuilder()
					->from('DOMJudgeBundle:Event', 'e')
					->select('e.eventid,e.eventtime,e.endpointtype,e.endpointid,e.datatype,e.dataid,e.action,e.content')
					->where('e.eventid > :lastIdSent')
					->setParameter('lastIdSent', $lastIdSent)
					->orderBy('e.eventid', 'ASC')
					->getQuery();
				$events = $q->getResult();
				foreach ($events as $event) {
					$data = json_decode(stream_get_contents($event['content']));
					echo json_encode(array(
						'id'        => $event['eventid'],
						'type'      => $event['endpointtype'],
						'op'        => $event['action'],
						'data'      => $data,
					)) . "\n";
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
