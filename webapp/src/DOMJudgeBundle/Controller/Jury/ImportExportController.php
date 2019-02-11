<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Form\Type\BaylorCmsType;
use DOMJudgeBundle\Form\Type\TsvImportType;
use DOMJudgeBundle\Service\BaylorCmsService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/import-export")
 * @Security("has_role('ROLE_ADMIN')")
 */
class ImportExportController extends BaseController
{
    /**
     * @var BaylorCmsService
     */
    protected $baylorCmsService;

    public function __construct(BaylorCmsService $baylorCmsService)
    {
        $this->baylorCmsService = $baylorCmsService;
    }

    /**
     * @Route("", name="jury_import_export")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request)
    {
        $tsvForm = $this->createForm(TsvImportType::class);

        $tsvForm->handleRequest($request);

        if ($tsvForm->isSubmitted() && $tsvForm->isValid()) {

        }

        $baylorForm = $this->createForm(BaylorCmsType::class);

        $baylorForm->handleRequest($request);

        if ($baylorForm->isSubmitted() && $baylorForm->isValid()) {
            $contestId   = $baylorForm->get('contest_id')->getData();
            $accessToken = $baylorForm->get('access_token')->getData();
            if ($baylorForm->get('fetch_teams')->isClicked()) {
                $this->baylorCmsService->importTeams($accessToken, $contestId);
                $this->addFlash('baylorCms', 'Teams successfully imported');
            } else {
                $this->baylorCmsService->uploadStandings($accessToken, $contestId);
                $this->addFlash('baylorCms', 'Standings successfully uploaded');
            }
            return $this->redirectToRoute('jury_import_export');
        }

        return $this->render('@DOMJudge/jury/import_export.html.twig', [
            'tsv_form' => $tsvForm->createView(),
            'baylor_form' => $baylorForm->createView(),
        ]);
    }

    /**
     * @Route("/contest-yaml", name="jury_contest_yaml_export")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function contestYamlAction()
    {
        return $this->render('@DOMJudge/jury/import_export_contest_yaml.html.twig');
    }

    /**
     * @Route("/export/{type}.tsv", name="jury_tsv_export", requirements={"type": "(groups|teams|scoreboard|results)"})
     * @param string $type
     */
    public function exportTsvAction(string $type)
    {

    }

    /**
     * @Route("/export/{type}.html", name="jury_html_export", requirements={"type":
     *                               "(results|results-icpc|clarifications)"})
     * @param string $type
     */
    public function exportHtmlAction(string $type)
    {

    }
}
