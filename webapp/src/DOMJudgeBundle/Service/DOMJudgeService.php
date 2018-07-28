<?php
namespace DOMJudgeBundle\Service;

use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;

use DOMJudgeBundle\Utils\Utils;

class DOMJudgeService
{
    protected $em;
    protected $request;
    protected $container;
    public function __construct(EntityManager $em, RequestStack $requestStack, Container $container)
    {
        $this->em = $em;
        $this->request = $requestStack->getCurrentRequest();
        $this->container = $container;
    }

    public function getCurrentContest()
    {
        $selected_cid = $this->request->cookies->get('domjudge_cid');
        $contests = $this->getCurrentContests();
        foreach ($contests as $contest) {
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
    public function dbconfig_get($name, $default = null)
    {
        if (is_null($name)) {
            $all_configs = $this->em->getRepository('DOMJudgeBundle:Configuration')->findAll();
            $ret = array();
            foreach ($all_configs as $config) {
                $ret[$config->getName()] = $config->getValue();
            }
            return $ret;
        }

        $config = $this->em->getRepository('DOMJudgeBundle:Configuration')->findOneByName($name);
        if (!empty($config)) {
            return $config->getValue();
        }

        if ($default===null) {
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
    public function getCurrentContests(
        $fulldata = false,
        $onlyofteam = null,
                                       $alsofuture = false,
        $key = 'cid'
    ) {
        $now = Utils::now();
        $qb = $this->em->createQueryBuilder();
        $qb->select('c')->from('DOMJudgeBundle:Contest', 'c');
        if ($onlyofteam !== null && $onlyofteam > 0) {
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
        } elseif ($onlyofteam === -1) {
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

        if (!$alsofuture) {
            $qb->andWhere($qb->expr()->lte('c.activatetime', $now));
        }

        $contests = $qb->getQuery()->getResult();
        return $contests;
    }

    public function checkrole($rolename, $check_superset = true)
    {
        $token = $this->container->get('security.token_storage')->getToken();
        if ($token == null) {
            return false;
        }
        $user =$token->getUser();

        // Ignore user objects if they aren't a DOMJudgeBundle user
        // Covers cases where users are not logged in
        if (!is_a($user, 'DOMJudgeBundle\Entity\User')) {
            return false;
        }

        $authchecker = $this->container->get('security.authorization_checker');
        if ($check_superset) {
            if ($authchecker->isGranted('ROLE_ADMIN') &&
                ($rolename == 'team' && $user->getTeam() != null)) {
                return true;
            }
        }
        return $authchecker->isGranted('ROLE_'.strtoupper($rolename));
    }

    public function getHttpKernel()
    {
        return $this->container->get('http_kernel');
    }
}
