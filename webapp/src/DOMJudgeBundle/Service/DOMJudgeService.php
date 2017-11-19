<?php
namespace DOMJudgeBundle\Service;

use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;


class DOMJudgeService {
	// Color names as defined by https://www.w3.org/TR/css3-color/#html4
	const HTML_COLORS = [
		"black" => "#000000",
		"silver" => "#C0C0C0",
		"gray" => "#808080",
		"white" => "#FFFFFF",
		"maroon" => "#800000",
		"red" => "#FF0000",
		"purple" => "#800080",
		"fuchsia" => "#FF00FF",
		"green" => "#008000",
		"lime" => "#00FF00",
		"olive" => "#808000",
		"yellow" => "#FFFF00",
		"navy" => "#000080",
		"blue" => "#0000FF",
		"teal" => "#008080",
		"aqua" => "#00FFFF",
		"aliceblue" => "#f0f8ff",
		"antiquewhite" => "#faebd7",
		"aquamarine" => "#7fffd4",
		"azure" => "#f0ffff",
		"beige" => "#f5f5dc",
		"bisque" => "#ffe4c4",
		"blanchedalmond" => "#ffebcd",
		"blueviolet" => "#8a2be2",
		"brown" => "#a52a2a",
		"burlywood" => "#deb887",
		"cadetblue" => "#5f9ea0",
		"chartreuse" => "#7fff00",
		"chocolate" => "#d2691e",
		"coral" => "#ff7f50",
		"cornflowerblue" => "#6495ed",
		"cornsilk" => "#fff8dc",
		"crimson" => "#dc143c",
		"cyan" => "#00ffff",
		"darkblue" => "#00008b",
		"darkcyan" => "#008b8b",
		"darkgoldenrod" => "#b8860b",
		"darkgray" => "#a9a9a9",
		"darkgreen" => "#006400",
		"darkgrey" => "#a9a9a9",
		"darkkhaki" => "#bdb76b",
		"darkmagenta" => "#8b008b",
		"darkolivegreen" => "#556b2f",
		"darkorange" => "#ff8c00",
		"darkorchid" => "#9932cc",
		"darkred" => "#8b0000",
		"darksalmon" => "#e9967a",
		"darkseagreen" => "#8fbc8f",
		"darkslateblue" => "#483d8b",
		"darkslategray" => "#2f4f4f",
		"darkslategrey" => "#2f4f4f",
		"darkturquoise" => "#00ced1",
		"darkviolet" => "#9400d3",
		"deeppink" => "#ff1493",
		"deepskyblue" => "#00bfff",
		"dimgray" => "#696969",
		"dimgrey" => "#696969",
		"dodgerblue" => "#1e90ff",
		"firebrick" => "#b22222",
		"floralwhite" => "#fffaf0",
		"forestgreen" => "#228b22",
		"gainsboro" => "#dcdcdc",
		"ghostwhite" => "#f8f8ff",
		"gold" => "#ffd700",
		"goldenrod" => "#daa520",
		"greenyellow" => "#adff2f",
		"grey" => "#808080",
		"honeydew" => "#f0fff0",
		"hotpink" => "#ff69b4",
		"indianred" => "#cd5c5c",
		"indigo" => "#4b0082",
		"ivory" => "#fffff0",
		"khaki" => "#f0e68c",
		"lavender" => "#e6e6fa",
		"lavenderblush" => "#fff0f5",
		"lawngreen" => "#7cfc00",
		"lemonchiffon" => "#fffacd",
		"lightblue" => "#add8e6",
		"lightcoral" => "#f08080",
		"lightcyan" => "#e0ffff",
		"lightgoldenrodyellow" => "#fafad2",
		"lightgray" => "#d3d3d3",
		"lightgreen" => "#90ee90",
		"lightgrey" => "#d3d3d3",
		"lightpink" => "#ffb6c1",
		"lightsalmon" => "#ffa07a",
		"lightseagreen" => "#20b2aa",
		"lightskyblue" => "#87cefa",
		"lightslategray" => "#778899",
		"lightslategrey" => "#778899",
		"lightsteelblue" => "#b0c4de",
		"lightyellow" => "#ffffe0",
		"limegreen" => "#32cd32",
		"linen" => "#faf0e6",
		"magenta" => "#ff00ff",
		"mediumaquamarine" => "#66cdaa",
		"mediumblue" => "#0000cd",
		"mediumorchid" => "#ba55d3",
		"mediumpurple" => "#9370db",
		"mediumseagreen" => "#3cb371",
		"mediumslateblue" => "#7b68ee",
		"mediumspringgreen" => "#00fa9a",
		"mediumturquoise" => "#48d1cc",
		"mediumvioletred" => "#c71585",
		"midnightblue" => "#191970",
		"mintcream" => "#f5fffa",
		"mistyrose" => "#ffe4e1",
		"moccasin" => "#ffe4b5",
		"navajowhite" => "#ffdead",
		"oldlace" => "#fdf5e6",
		"olivedrab" => "#6b8e23",
		"orange" => "#ffa500",
		"orangered" => "#ff4500",
		"orchid" => "#da70d6",
		"palegoldenrod" => "#eee8aa",
		"palegreen" => "#98fb98",
		"paleturquoise" => "#afeeee",
		"palevioletred" => "#db7093",
		"papayawhip" => "#ffefd5",
		"peachpuff" => "#ffdab9",
		"peru" => "#cd853f",
		"pink" => "#ffc0cb",
		"plum" => "#dda0dd",
		"powderblue" => "#b0e0e6",
		"rosybrown" => "#bc8f8f",
		"royalblue" => "#4169e1",
		"saddlebrown" => "#8b4513",
		"salmon" => "#fa8072",
		"sandybrown" => "#f4a460",
		"seagreen" => "#2e8b57",
		"seashell" => "#fff5ee",
		"sienna" => "#a0522d",
		"skyblue" => "#87ceeb",
		"slateblue" => "#6a5acd",
		"slategray" => "#708090",
		"slategrey" => "#708090",
		"snow" => "#fffafa",
		"springgreen" => "#00ff7f",
		"steelblue" => "#4682b4",
		"tan" => "#d2b48c",
		"thistle" => "#d8bfd8",
		"tomato" => "#ff6347",
		"turquoise" => "#40e0d0",
		"violet" => "#ee82ee",
		"wheat" => "#f5deb3",
		"whitesmoke" => "#f5f5f5",
		"yellowgreen" => "#9acd32",
	];

	protected $em;
	protected $request;
	protected $container;
	public function __construct(EntityManager $em, RequestStack $requestStack, Container $container) {
		$this->em = $em;
		$this->request = $requestStack->getCurrentRequest();
		$this->container = $container;
	}

	public function getCurrentContest() {
		$selected_cid = $this->request->cookies->get('domjudge_cid');
		$contests = $this->getCurrentContests();
		foreach($contests as $contest) {
			if ($contest->getCid() == $selected_cid) {
				return $contest;
			}
		}
		if (count($contests) > 0) {
			return $contests[0];
		}
		return null;
	}

	/**
	 * Query configuration variable, with optional default value in case
	 * the variable does not exist and boolean to indicate if cached
	 * values can be used.
	 *
	 * When $name is null, then all variables will be returned.
	 */
	function dbconfig_get($name, $default = null)
	{
		if ( is_null ($name) ) {
			$all_configs = $this->em->getRepository('DOMJudgeBundle:Configuration')->findAll();
			$ret = array();
			foreach ( $all_configs as $config ) {
				$ret[$config->getName()] = $config->getValue();
			}
			return $ret;
		}

		$config = $this->em->getRepository('DOMJudgeBundle:Configuration')->findOneByName($name);
		if (!empty($config)) {
			return $config->getValue();
		}

		if ( $default===null ) {
			throw new \Exception("Configuration variable '$name' not found.");
		}
		return $default;
	}

	/**
	 * Will return all the contests that are currently active.
	 * When fulldata is true, returns the total row as an array
	 * instead of just the ID (array indices will be contest ID's then).
	 * If $onlyofteam is not null, only show contests that team is part
	 * of. If it is -1, only show publicly visible contests.
	 * If $alsofuture is true, also show the contests that start in the future.
	 * The results will have the value of field $key in the database as key.
	 *
	 * This is equivalent to $cdata in the old codebase.
	 */
	public function getCurrentContests($fulldata = FALSE, $onlyofteam = NULL,
	                                   $alsofuture = FALSE, $key = 'cid') {

		$now = time();
		$qb = $this->em->createQueryBuilder();
		$qb->select('c')->from('DOMJudgeBundle:Contest', 'c');
		if ( $onlyofteam !== null && $onlyofteam > 0 ) {
			$qb->leftJoin('DOMJudgeBundle:ContestTeam', 'ct')
			   ->where('ct.teamid = :teamid')
			   ->setParameter('teamid', $onlyofteam);
			// $contests = $DB->q("SELECT * FROM contest
			//                     LEFT JOIN contestteam USING (cid)
			//                     WHERE (contestteam.teamid = %i OR contest.public = 1)
			//                     AND enabled = 1 ${extra}
			//                     AND ( deactivatetime IS NULL OR
			//                           deactivatetime > UNIX_TIMESTAMP() )
			//                     ORDER BY activatetime", $onlyofteam);
		} elseif ( $onlyofteam === -1 ) {
			$qb->addWhere('c.public = 1');
			// $contests = $DB->q("SELECT * FROM contest
			//                     WHERE enabled = 1 AND public = 1 ${extra}
			//                     AND ( deactivatetime IS NULL OR
			//                           deactivatetime > UNIX_TIMESTAMP() )
			//                     ORDER BY activatetime");
		}
		$qb->andWhere('c.enabled = 1')
		   ->andWhere($qb->expr()->orX(
		       'c.deactivatetime is null',
		       $qb->expr()->gt('c.deactivatetime', $now)
		   ))
		   ->orderBy('c.activatetime');

		if ( !$alsofuture ) {
			$qb->andWhere($qb->expr()->lte('c.activatetime',$now));
		}

		$contests = $qb->getQuery()->getResult();
		return $contests;
	}

	public function checkrole($rolename, $check_superset = TRUE) {
		$token = $this->container->get('security.token_storage')->getToken();
		if ($token == null) return false;
		$user =$token->getUser();

		// Ignore user objects if they aren't a DOMJudgeBundle user
		// Covers cases where users are not logged in
		if (!is_a($user, 'DOMJudgeBundle\Entity\User')) {
			return false;
		}

		$authchecker = $this->container->get('security.authorization_checker');
		if ($check_superset) {
			if ($authchecker->isGranted('ROLE_ADMIN') &&
				($rolename == 'team' && $user->getTeam() != NULL)) {
					return true;
			}
		}
		return $authchecker->isGranted('ROLE_'.strtoupper($rolename));
	}

	public function getHttpKernel()
	{
		return $this->container->get('http_kernel');
	}

	/**
	 * Convert a HTML extended color name to 6-digit hex RGB value.
	 * If $color is already in hex RGB format, it is returned unchanged.
	 * Returns NULL if $color is not valid.
	 */
	public static function colorToHex($color)
	{
		if ( preg_match('/^#([[:xdigit:]]{3}){1,2}$/', $color) ) return $color;

		$color = strtolower(preg_replace('/[[:space:]]/','',$color));
		if ( isset(self::HTML_COLORS[$color]) ) return strtoupper(self::HTML_COLORS[$color]);
		return null;
	}

	/**
	 * Convert a hexadecimal RGB color code to the closest HTML color
	 * name. Returns NULL if $hex is not a valid 3 or 6 digit hex RGB
	 * string starting with a '#'.
	 */
	public static function hexToColor($hex)
	{
		// Expand short 3 digit hex version.
		if ( preg_match('/^#[[:xdigit:]]{3}$/', $hex) ) {
			$new = '#';
			for($i=1; $i<=3; $i++) $new .= str_repeat($hex[$i],2);
			$hex = $new;
		}
		if ( !preg_match('/^#[[:xdigit:]]{6}$/', $hex) ) return NULL;

		// Find the best match in L1 distance.
		$bestmatch = '';
		$bestdist = 999999;

		foreach ( self::HTML_COLORS as $color => $rgb ) {
			$dist = 0;
			for($i=1; $i<=3; $i++) {
				sscanf(substr($hex,2*$i-1,2),'%x',$val1);
				sscanf(substr($rgb,2*$i-1,2),'%x',$val2);
				$dist += abs($val1 - $val2);
			}
			if ( $dist<$bestdist ) {
				$bestdist = $dist;
				$bestmatch = $color;
			}
		}

		return $bestmatch;
	}

}
