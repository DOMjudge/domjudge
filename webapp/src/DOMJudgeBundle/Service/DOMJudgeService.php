<?php
namespace DOMJudgeBundle\Service;

use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\RequestStack;


class DOMJudgeService {
  protected $em;
  protected $request;
  public function __construct(EntityManager $em, RequestStack $requestStack) {
      $this->em = $em;
      $this->request = $requestStack->getCurrentRequest();
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

  public function getCurrentContests($fulldata = FALSE, $onlyofteam = NULL,
                                     $alsofuture = FALSE, $key = 'cid') {
    // This is equivalent to $cdata in the old codebase

    /**
     * Will return all the contests that are currently active
     * When fulldata is true, returns the total row as an array
     * instead of just the ID (array indices will be contest ID's then).
     * If $onlyofteam is not null, only show contests that team is part
     * of. If it is -1, only show publicly visible contests
     * If $alsofuture is true, also show the contests that start in the future
     * The results will have the value of field $key in the database as key
     */
      $now = time();
      $qb = $this->em->createQueryBuilder();
      $qb->select('c')->from('DOMJudgeBundle:Contest', 'c');
    	if ( $onlyofteam !== null && $onlyofteam > 0 ) {
        $qb->leftJoin('DOMJudgeBundle:ContestTeam', 'ct')
           ->where('ct.teamid = :teamid')
           ->setParamter('teamid', $onlyofteam);
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

}
