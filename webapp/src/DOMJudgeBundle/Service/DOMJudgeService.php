<?php declare(strict_types=1);
namespace DOMJudgeBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\AuditLog;
use DOMJudgeBundle\Entity\Configuration;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Utils\Utils;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use Symfony\Component\HttpFoundation\RequestStack;

class DOMJudgeService
{
    protected $em;
    protected $request;
    protected $container;
    protected $hasAllRoles = false;

    public function __construct(EntityManagerInterface $em, RequestStack $requestStack, Container $container)
    {
        $this->em = $em;
        $this->request = $requestStack->getCurrentRequest();
        $this->container = $container;
    }

    public function getCurrentContest()
    {
        $selected_cid = $this->request->cookies->get('domjudge_cid');
        if ($selected_cid == -1) return null;

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
     * @param string|null $name
     * @param mixed $default
     * @param bool $onlyifpublic
     * @return Configuration[]|mixed
     * @throws \Exception
     */
    public function dbconfig_get($name, $default = null, bool $onlyifpublic = false)
    {
        if (is_null($name)) {
            $all_configs = $this->em->getRepository('DOMJudgeBundle:Configuration')->findAll();
            $ret         = array();
            /** @var Configuration $config */
            foreach ($all_configs as $config) {
                if (!$onlyifpublic || $config->getPublic()) {
                    $ret[$config->getName()] = $config->getValue();
                }
            }
            return $ret;
        }

        /** @var Configuration $config */
        $config = $this->em->getRepository('DOMJudgeBundle:Configuration')->findOneByName($name);
        if (!empty($config) && (!$onlyifpublic || $config->getPublic())) {
            return $config->getValue();
        }

        if ($default === null) {
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
        bool $fulldata = false,
        $onlyofteam = null,
        bool $alsofuture = false,
        string $key = 'cid'
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
            $qb->andWhere('c.public = 1');
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

    /**
     * Get the contest with the given contest ID
     * @param int $cid
     * @return Contest|null
     */
    public function getContest($cid)
    {
        return $this->em->getRepository(Contest::class)->find($cid);
    }

    /**
     * Get the team with the given team ID
     * @param int $teamid
     * @return Team|null
     */
    public function getTeam($teamid)
    {
        return $this->em->getRepository(Team::class)->find($teamid);
    }

    public function checkrole(string $rolename, bool $check_superset = true) : bool
    {
        if ($this->hasAllRoles) {
            return true;
        }

        $user = $this->getUser();
        if ($user === null) {
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

    /**
     * Get the logged in user
     * @return User|null
     */
    public function getUser()
    {
        $token = $this->container->get('security.token_storage')->getToken();
        if ($token == null) {
            return null;
        }

        $user = $token->getUser();

        // Ignore user objects if they aren't a DOMJudgeBundle user
        // Covers cases where users are not logged in
        if (!is_a($user, 'DOMJudgeBundle\Entity\User')) {
            return null;
        }

        return $user;
    }

    public function getCookie(string $cookieName) {
      if (!$this->request->cookies) {
        return null;
      }
      return $this->request->cookies->get($cookieName);
    }

    public function getUpdates() {
      $contest = $this->getCurrentContest();

      $clarifications = array();
      if ($contest) {
        $clarifications = $this->em->createQueryBuilder()
          ->select('clar')
          ->from('DOMJudgeBundle:Clarification', 'clar')
          ->where('clar.contest = :contest')
          ->andWhere('clar.sender is not null')
          ->andWhere('clar.answered = 0')
          ->setParameter('contest', $contest)
          ->getQuery()->getResult();
      }
      $judgehosts = $this->em->createQueryBuilder()
        ->select('j')
        ->from('DOMJudgeBundle:Judgehost', 'j')
        ->where('j.active = 1')
        ->andWhere('j.polltime < :i')
        ->setParameter('i', time() - $this->dbconfig_get('judgehost_critical', 120))
        ->getQuery()->getResult();

      $rejudgings = $this->em->createQueryBuilder()
        ->select('r')
        ->from('DOMJudgeBundle:Rejudging', 'r')
        ->where('r.endtime is null')
        ->getQuery()->getResult();

      $internal_error = $this->em->createQueryBuilder()
        ->select('ie')
        ->from('DOMJudgeBundle:InternalError', 'ie')
        ->where('ie.status = :status')
        ->setParameter('status', 'open')
        ->getQuery()->getResult();

      return array(
        'clarifications' => $clarifications,
        'judgehosts' => $judgehosts,
        'rejudgings' => $rejudgings,
        'internal_error' => $internal_error,
      );
    }

    public function getHttpKernel()
    {
        return $this->container->get('http_kernel');
    }

    /**
     * @return bool
     */
    public function getHasAllRoles(): bool
    {
        return $this->hasAllRoles;
    }

    /**
     * @param bool $hasAllRoles
     */
    public function setHasAllRoles(bool $hasAllRoles)
    {
        $this->hasAllRoles = $hasAllRoles;
    }

    /**
     * Log an action to the auditlog table
     *
     * @param string $datatype
     * @param mixed $dataid
     * @param string $action
     * @param mixed|null $extraInfo
     * @param mixed|null $forceUsername
     * @param int|null $cid
     */
    public function auditlog(string $datatype, $dataid, string $action, $extraInfo = null, $forceUsername = null, $cid = null)
    {
        if (!empty($forceUsername)) {
            $user = $forceUsername;
        } else {
            $user = $this->getUser() ? $this->getUser()->getUsername() : null;
        }

        $auditLog = new AuditLog();
        $auditLog
            ->setLogtime(now())
            ->setCid($cid)
            ->setUser($user)
            ->setDatatype($datatype)
            ->setDataid($dataid)
            ->setAction($action)
            ->setExtrainfo($extraInfo);

        $this->em->persist($auditLog);
        $this->em->flush();
    }
}
