<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Form\Type\PrintType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON')"))]
#[Route(path: '/jury/print')]
class PrintController extends BaseController
{
    public function __construct(
        EntityManagerInterface $em,
        protected readonly EventLogService $eventLogService,
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        KernelInterface $kernel,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    #[Route(path: '', name: 'jury_print')]
    public function showAction(Request $request): Response
    {
        if (!$this->config->get('print_command')) {
            throw new AccessDeniedHttpException("Printing disabled in config");
        }

        $form = $this->createForm(PrintType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            /** @var UploadedFile $file */
            $file             = $data['code'];
            $realfile         = $file->getRealPath();
            $originalfilename = $file->getClientOriginalName();

            $langid   = $data['langid'];

            // Since this is the Jury interface, there's not necessarily a
            // team involved; do not set a teamname or location.
            $ret = $this->dj->printUserFile($realfile, $originalfilename, $langid);

            return $this->render('jury/print_result.html.twig', [
                'success' => $ret[0],
                'output' => $ret[1],
            ]);
        }

        return $this->render('jury/print.html.twig', [
            'form' => $form,
        ]);
    }
}
