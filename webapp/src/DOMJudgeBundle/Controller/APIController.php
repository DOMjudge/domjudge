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



use DOMJudgeBundle\Entity\Contest;

/**
 * @Route("/api", defaults={ "_format" = "json" })
 */
class APIController extends FOSRestController {

	public $apiVersion = 4;
	// FIXME: get DOMjudge version from central location
	public $domjudgeVersion = "5.2.0DEV";

	// prints the absolute time as yyyy-mm-ddThh:mm:ss(.uuu)?[+-]zz(:mm)?
	// (with millis if $floored is false)
	private static function absTime($seconds, $floored = FALSE) {
		$millis = sprintf(".%03d", 1000*($seconds - floor($seconds)));
		return date("Y-m-d\TH:i:s", $seconds)
			. ( $floored ? '' : $millis )
			. date("P", $seconds);
	}

	// prints a time diff as relative time as (-)?(h)*h:mm:ss(.uuu)?
	// (with millis if $floored is false)
	private static function relTime($seconds, $floored = FALSE) {
		$res = ( $seconds < 0 ) ? '-' : '';
		$seconds = abs($seconds);
		$hours = (int)($seconds / 3600);
		$minutes = (int)(($seconds - $hours*3600)/60);
		$millis = sprintf(".%03d", 1000*($seconds - floor($seconds)));
		$seconds = $seconds - $hours*3600 - $minutes*60;
		return sprintf("%d:%02d:%02d", $hours, $minutes, $seconds)
			. ( $floored ? '' : $millis );
	}

	// helper function to convert the data in the Contest object to the specified values
	private static function cdataHelper(Contest $contest) {
		return [
			'id'                         => $contest->getCid(),
			'external_id'                => $contest->getExternalId(),
			'shortname'                  => $contest->getShortname(),
			'name'                       => $contest->getName(),
			'formal_name'                => $contest->getName(),
			'start_time'                 => self::absTime($contest->getStarttime()),
			'end_time'                   => self::absTime($contest->getEndtime()),
			'duration'                   => self::relTime($contest->getEndtime() - $contest->getStarttime()),
			'scoreboard_freeze_duration' => self::relTime($contest->getEndtime() - $contest->getFreezetime()),
			'unfreeze'                   => self::absTime($contest->getUnfreezetime()),
			'penalty'                    => 20, // FIXME
		];
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
			'domjudge_version' => $this->domjudgeVersion,
		];
		return $data;
	}

	/**
	 * @Get("/contests")
	 * @View(serializerGroups={"details","problems"})
	 */
	public function getContestsAction() {
		$em = $this->getDoctrine()->getManager();
		$data = $em->getRepository(Contest::class)->findAll();

		return array_map("self::cdataHelper", $data);
	}

	/**
	 * @Get("/contests/{cid}")
	 * @View(serializerGroups={"details","problems"})
	 */
	public function getSingleContestAction(Contest $cid) {
		return self::cdataHelper($cid);
	}
}
