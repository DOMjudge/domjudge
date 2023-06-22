<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
/**
 * @Route("/public/help")
 */
class HelpController extends BaseController
{
    /**
     * @Route("", name="public_help")
     */
    public function helpAction(): Response
    {
        return $this->render('public/help.html.twig');
    }
}
