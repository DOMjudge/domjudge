<?php

namespace DOMjudge\JuryBundle\Controller;

use Doctrine\ORM\Query;
use DOMjudge\JuryBundle\Form\Type\TeamType;
use DOMjudge\MainBundle\Entity\Team;
use DOMjudge\MainBundle\Form\Type\ConfirmType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TeamController extends Controller
{
	/**
	 * @Route("/teams", name="jury_team_list")
	 * @Template()
	 */
	public function listAction()
	{
		$collate = $this->getParameter('mysql_collation');
		$qb = $this->get('doctrine.orm.default_entity_manager')->createQueryBuilder();
		$query = $qb
			->select('t')
			->addSelect('c')
			->addSelect('a')
			->addSelect('COUNT(co.cid) AS numContests, COLLATE(t.name, ' . $collate . ') AS orderName')
			->from('DOMjudgeMainBundle:Team', 't')
			->leftJoin('t.contests', 'co')
			->leftJoin('t.category', 'c')
			->leftJoin('t.affiliation', 'a')
			->groupBy('t.teamid')
			->orderBy('c.sortOrder')
			->addOrderBy('orderName')
			->getQuery();

		$teams = $query->getResult();

		// Add public contest count
		$query = $this->get('doctrine.orm.default_entity_manager')->createQueryBuilder()
			->select('COUNT(c) AS contestcount')
			->from('DOMjudgeMainBundle:Contest', 'c')
			->where('c.public = 1')
			->getQuery();

		$publicContestCount = $query->getSingleScalarResult();
		foreach ( $teams as &$team ) {
			$team['numContests'] += $publicContestCount;
		}
		unset($team);

		$contests = $this->get('domjudge.contest')->getActiveContests(null, false, null, true);

		$query = $this->get('doctrine.orm.default_entity_manager')->createQueryBuilder()
			->select('COUNT(s) AS cnt, t')// We need to also select the team to be able to index by teamid
			->from('DOMjudgeMainBundle:Team', 't', 't.teamid')
			->innerJoin('t.submissions', 's')
			->where('s.contest IN (:contests)')
			->setParameter('contests', $contests)
			->groupBy('s.team')
			->getQuery();

		$numSubmits = $query->getResult(Query::HYDRATE_ARRAY);
		foreach ( $teams as &$team ) {
			if ( isset($numSubmits[$team[0]->getTeamid()]) ) {
				$team['numSubmit'] = (int)$numSubmits[$team[0]->getTeamid()]['cnt'];
			}
		}
		unset($team);

		$query = $this->get('doctrine.orm.default_entity_manager')->createQueryBuilder()
			->select('COUNT(s) AS cnt, t')// We need to also select the team to be able to index by teamid
			->from('DOMjudgeMainBundle:Team', 't', 't.teamid')
			->innerJoin('t.submissions', 's')
			->innerJoin('s.judgings', 'j')
			->where('s.contest IN (:contests)')
			->andWhere('j.valid = 1')
			->andWhere('j.result = \'correct\'')
			->setParameter('contests', $contests)
			->groupBy('s.team')
			->getQuery();

		$numCorrects = $query->getResult(Query::HYDRATE_ARRAY);
		foreach ( $teams as &$team ) {
			if ( isset($numCorrects[$team[0]->getTeamid()]) ) {
				$team['numCorrect'] = (int)$numCorrects[$team[0]->getTeamid()]['cnt'];
			}
		}
		unset($team);

		return array(
			'teams' => $teams,
		);
	}

	/**
	 * @Route("/team/{team}", name="jury_team_view")
	 * @Template()
	 */
	public function viewAction(Team $team)
	{
		return array(
			'team' => $team,
		);
	}

	/**
	 * @Route("/team/{team}/edit", name="jury_team_edit")
	 * @Security(expression="has_role('ROLE_ADMIN')")
	 * @Template()
	 */
	public function editAction(Team $team, Request $request)
	{
		$form = $this->createForm(TeamType::class, $team);

		return $this->processTeam($team, $request);
	}

	/**
	 * @Route("/team/{team}/delete", name="jury_team_delete")
	 * @Security(expression="has_role('ROLE_ADMIN')")
	 * @Template()
	 */
	public function deleteAction(Team $team, Request $request)
	{
		$data = array();
		$form = $this->createForm(ConfirmType::class, $data);

		$form->handleRequest($request);

		if ( $form->isValid() && $form->isSubmitted() ) {
			if ( $form->get('yesimsure')->isClicked() ) {
				$em = $this->getDoctrine()->getManager();
				$em->remove($team);
				$em->flush();
			}

			return $this->redirectToRoute('jury_team_list');
		}

		return array(
			'team' => $team,
			'form' => $form->createView(),
		);
	}

	/**
	 * @Route("/teams/create", name="jury_team_create")
	 * @Security(expression="has_role('ROLE_ADMIN')")
	 * @Template()
	 */
	public function createAction(Request $request)
	{
		$team = new Team();

		return $this->processTeam($team, $request);
	}

	private function processTeam(Team $team, Request $request)
	{
		$form = $this->createForm(TeamType::class, $team);

		$form->handleRequest($request);

		if ( $form->isValid() && $form->isSubmitted() ) {
			$em = $this->getDoctrine()->getManager();
			$em->persist($team);
			$em->flush();

			return $this->redirectToRoute('jury_team_list');
		}

		return array(
			'form' => $form->createView(),
			'team' => $team,
		);
	}
}
