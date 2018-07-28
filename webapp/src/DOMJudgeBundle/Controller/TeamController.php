<?php

namespace DOMJudgeBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use DOMJudgeBundle\Entity\Team;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * @Route("/jury-v2")
 * @Security("has_role('ROLE_JURY')")
 */
class TeamController extends Controller
{
    /**
     *@Route("/teams/", name="teams_index")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $teams = $em->getRepository('DOMJudgeBundle:Team')->findAll();
        // replace this example code with whatever you need
        return $this->render('DOMJudgeBundle:team:index.html.twig', [
            'teams' => $teams
        ]);
    }

    /**
     *@Route("/teams/{teamid}", name="team_show")
     */
    public function showAction(Request $request, Team $team)
    {
        // replace this example code with whatever you need
        return $this->render('DOMJudgeBundle:team:show.html.twig', [
            'team' => $team
        ]);
    }
}
