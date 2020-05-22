<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Language;
use App\Form\Type\PrintType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class PrintController
 *
 * @Route("/jury/print")
 * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON')")
 *
 * @package App\Controller\Jury
 */
class PrintController extends BaseController
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * PrintController constructor.
     *
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param ConfigurationService   $config
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config
    ) {
        $this->em     = $em;
        $this->dj     = $dj;
        $this->config = $config;
    }

    /**
     * @Route("", name="jury_print")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function showAction(Request $request)
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
            $originalfilename = $file->getClientOriginalName() ?? '';

            $langid   = $data['langid'];
            $username = $this->getUser()->getUsername();

            // Since this is the Jury interface, there's not necessarily a
            // team involved; do not set a teamname or location.
            $ret = $this->dj->printFile($realfile, $originalfilename, $langid, $username);

            return $this->render('jury/print_result.html.twig', [
                'success' => $ret[0],
                'output' => $ret[1],
            ]);
        }

        /** @var Language[] $languages */
        $languages = $this->em->createQueryBuilder()
            ->from(Language::class, 'l')
            ->select('l')
            ->andWhere('l.allowSubmit = 1')
            ->getQuery()
            ->getResult();

        return $this->render('jury/print.html.twig', [
            'form' => $form->createView(),
            'languages' => $languages,
        ]);
    }
}
