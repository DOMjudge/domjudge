<?php

namespace App\Cloudcontest\AdminerBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class AdminerController extends AbstractController
{
  /**
   * @Route("/jury/adminer", name="jury_adminer")
   * @IsGranted("ROLE_ADMIN")
   */
  public function adminer(Request $request) {
    ob_start();
    include_once $this->getParameter('kernel.project_dir') . '/src/Cloudcontest/AdminerBundle/Resources/views/adminer.html.php';
    $resp = ob_get_contents();
    ob_end_clean();

    return new Response($resp);
  }
}
