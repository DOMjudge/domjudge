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
class APIController extends FOSRestController
{
    public $apiVersion = 4;

    /**
     * @Get("/")
     */
    public function getCurrentActiveContestAction()
    {
        $contests = $this->getContestsAction();
        if (count($contests) == 0) {
            return null;
        } else {
            return $contests[0];
        }
    }

    /**
     * @Patch("/contests/{cid}")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function changeStartTimeAction(Request $request, Contest $contest)
    {
        $args = $request->request->all();
        $response = null;
        $now = Utils::now();
        if (!isset($args['id'])) {
            $response = new Response('Missing "id" in request.', 400);
        } elseif (!array_key_exists('start_time', $args)) {
            $response = new Response('Missing "start_time" in request.', 400);
        } elseif ($args['id'] != $contest->getCid()) {
            $response = new Response('Invalid "id" in request.', 400);
        } elseif (!isset($args['force']) &&
                  $contest->getStarttime() != null &&
                  $contest->getStarttime() < $now + 30) {
            $response = new Response('Current contest already started or about to start.', 403);
        } elseif ($args['start_time'] === null) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($contest);
            $contest->setStarttimeEnabled(false);
            $response = new Response('Contest paused :-/.', 200);
            $em->flush();
        } else {
            $em = $this->getDoctrine()->getManager();
            $date = date_create($args['start_time']);
            if ($date === false) {
                $response = new Response('Invalid "start_time" in request.', 400);
            } else {
                $new_start_time = $date->getTimestamp();
                if (!isset($args['force']) && $new_start_time < $now + 30) {
                    $response = new Response('New start_time not far enough in the future.', 403);
                } else {
                    $em->persist($contest);
                    $newStartTimeString = date('Y-m-d H:i:s e', $new_start_time);
                    $contest->setStarttimeEnabled(true);
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
    public function getVersionAction()
    {
        $data = ['api_version' => $this->apiVersion];
        return $data;
    }

    /**
     * @Get("/info")
     */
    public function getInfoAction()
    {
        $data = [
            'api_version' => $this->apiVersion,
            'domjudge_version' => $this->getParameter('domjudge.version'),
        ];
        return $data;
    }

    /**
     * @Get("/contests")
     */
    public function getContestsAction()
    {
        $request = Request::createFromGlobals();
        $strict = false;
        if ($request->query->has('strict')) {
            $strict = $request->query->getBoolean('strict');
        }
        $em = $this->getDoctrine()->getManager();
        $data = $em->getRepository(Contest::class)->findBy(
            array(
                'enabled' => true,
                'public' => true,
            )
        );
        $penalty_time = $this->get('domjudge.domjudge')->dbconfig_get('penalty_time', 20);

        $result = [];
        foreach ($data as $contest) {
            if ($contest->isActive()) {
                $result[] = $contest->serializeForAPI($penalty_time, $strict);
            }
        }

        return $result;
    }

    /**
     * @Get("/contests/{cid}")
     */
    public function getSingleContestAction(Contest $contest)
    {
        $request = Request::createFromGlobals();
        $strict = false;
        if ($request->query->has('strict')) {
            $strict = $request->query->getBoolean('strict');
        }
        $isJury = $this->isGranted('ROLE_JURY');
        if (($isJury && $contest->getEnabled())
            || (!$isJury && $contest->isActive())) {
            $penalty_time = $this->get('domjudge.domjudge')->dbconfig_get('penalty_time', 20);
            return $contest->serializeForAPI($penalty_time, $strict);
        } else {
            return null;
        }
    }

    /**
     * @Get("/contests/{cid}/contest-yaml")
     */
    public function getContestYaml(Contest $contest)
    {
        $penalty_time = $this->get('domjudge.domjudge')->dbconfig_get('penalty_time', 20);
        $response = new StreamedResponse();
        $response->setCallback(function () use ($contest, $penalty_time) {
            echo "name:                     " . $contest->getName() . "\n";
            echo "short-name:               " . $contest->getExternalid() . "\n";
            echo "start-time:               " . Utils::absTime($contest->getStarttime(), true) . "\n";
            echo "duration:                 " . Utils::relTime($contest->getEndtime() - $contest->getStarttime(), true) . "\n";
            echo "scoreboard-freeze-length: " . Utils::relTime($contest->getEndtime() - $contest->getFreezetime(), true) . "\n";
            echo "penalty-time:             " . $penalty_time . "\n";
        });
        $response->headers->set('Content-Type', 'text-plain');
        $response->headers->set('Content-Disposition', 'attachment; filename="contest.yaml"');
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Connection', 'Keep-Alive');
        $response->headers->set('Accept-Ranges', 'bytes');

        return $response;
    }

    /**
     * @Get("/contests/{cid}/state")
     */
    public function getContestState(Contest $contest)
    {
        $isJury = $this->isGranted('ROLE_JURY');
        if (($isJury && $contest->getEnabled())
            || (!$isJury && $contest->isActive())) {
            $time_or_null = function ($time, $extra_cond = true) {
                if (!$extra_cond || $time===null || Utils::now()<$time) {
                    return null;
                }
                return Utils::absTime($time);
            };
            $result = [];
            $result['started']   = $time_or_null($contest->getStarttime());
            $result['ended']     = $time_or_null($contest->getEndtime(), $result['started']!==null);
            $result['frozen']    = $time_or_null($contest->getFreezetime(), $result['started']!==null);
            $result['thawed']    = $time_or_null($contest->getUnfreezetime(), $result['frozen']!==null);
            $result['finalized'] = $time_or_null($contest->getFinalizetime(), $result['ended']!==null);
            $result['end_of_updates'] = null;
            if ($result['finalized']!==null &&
                ($result['thawed']!==null || $result['frozen']===null)) {
                if ($result['thawed']!==null &&
                    $contest->getFreezetime()>$contest->getFinalizetime()) {
                    $result['end_of_updates'] = $result['thawed'];
                } else {
                    $result['end_of_updates'] = $result['finalized'];
                }
            }
            return $result;
        } else {
            return null;
        }
    }

    /**
     * @Get("/contests/{cid}/event-feed")
     * @Security("has_role('ROLE_JURY')")
     */
    public function getEventFeed(Request $request, Contest $contest)
    {
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
            if ($event===null) {
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
            while (true) {
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
                if (!$isJury) {
                    $restricted_types = ['judgements', 'runs', 'clarifications'];
                    if ($contest->getStarttime() === null ||
                        Utils::now() < $contest->getStarttime()) {
                        $restricted_types[] = 'problems';
                    }
                    $qb = $qb
                        ->andWhere('e.endpointtype NOT IN (:restricted_types)')
                        ->setParameter(':restricted_types', $restricted_types);
                }

                $q = $qb->getQuery();

                $events = $q->getResult();
                foreach ($events as $event) {
                    // FIXME: use the dj_* wrapper as in lib/lib.wrapper.php.
                    $data = json_decode(stream_get_contents($event['content']), true);
                    // Filter fields with specific access restrictions.
                    if (!$isJury) {
                        if ($event['endpointtype'] == 'submissions') {
                            unset($data['entry_point']);
                            unset($data['language_id']);
                        }
                        if ($event['endpointtype'] == 'problems') {
                            unset($data['test_data_count']);
                        }
                    }
                    $result = array(
                        'id'        => (string)$event['eventid'],
                        'type'      => (string)$event['endpointtype'],
                        'op'        => (string)$event['action'],
                        'data'      => $data,
                    );
                    if (!$strict) {
                        $result['time'] = Utils::absTime($event['eventtime']);
                    }
                    echo json_encode($result, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES) . "\n";
                    ob_flush();
                    flush();
                    $lastUpdate = Utils::now();
                    $lastIdSent = $event['eventid'];
                }

                if (count($events) == 0) {
                    if (!$stream) {
                        break;
                    }
                    // No new events, check if it's time for a keep alive.
                    $now = Utils::now();
                    if ($lastUpdate + 60 < $now) {
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
