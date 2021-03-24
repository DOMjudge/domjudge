<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Executable;
use App\Entity\ExecutableFile;
use App\Entity\ImmutableExecutable;
use App\Form\Type\ExecutableType;
use App\Form\Type\ExecutableUploadType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;
use ZipArchive;

/**
 * @Route("/jury/executables")
 * @IsGranted("ROLE_JURY")
 */
class ExecutableController extends BaseController
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
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        KernelInterface $kernel,
        EventLogService $eventLogService
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
        $this->config          = $config;
        $this->kernel          = $kernel;
        $this->eventLogService = $eventLogService;
    }

    /**
     * @Route("", name="jury_executables")
     */
    public function indexAction(Request $request) : Response
    {
        $data = [];
        $form = $this->createForm(ExecutableUploadType::class, $data);
        $form->handleRequest($request);

        $em = $this->em;
        /** @var Executable[] $executables */
        $executables      = $em->createQueryBuilder()
            ->select('e as executable, e.execid as execid')
            ->from(Executable::class, 'e')
            ->orderBy('e.execid', 'ASC')
            ->getQuery()->getResult();
        $executables      = array_column($executables, 'executable', 'execid');
        $table_fields     = [
            'execid' => ['title' => 'ID', 'sort' => true,],
            'type' => ['title' => 'type', 'sort' => true,],
            'description' => ['title' => 'description', 'sort' => true, 'default_sort' => true],
        ];

        $propertyAccessor  = PropertyAccess::createPropertyAccessor();
        $executables_table = [];
        foreach ($executables as $e) {
            $execdata    = [];
            $execactions = [];
            // Get whatever fields we can from the team object itself
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($e, $k)) {
                    $execdata[$k] = ['value' => $propertyAccessor->getValue($e, $k)];
                }
            }

            if ($this->isGranted('ROLE_ADMIN')) {
                $execactions[] = [
                    'icon' => 'edit',
                    'title' => 'edit this executable',
                    'link' => $this->generateUrl('jury_executable_edit', ['execId' => $e->getExecid()])
                ];
                $execactions[] = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this executable',
                    'link' => $this->generateUrl('jury_executable_delete', [
                        'execId' => $e->getExecid(),
                    ]),
                    'ajaxModal' => true,
                ];
            }
            $execactions[] = [
                'icon' => 'file-download',
                'title' => 'download this executable',
                'link' => $this->generateUrl('jury_executable_download', ['execId' => $e->getExecid()])
            ];

            $executables_table[]            = [
                'data' => $execdata,
                'actions' => $execactions,
                'link' => $this->generateUrl('jury_executable', ['execId' => $e->getExecid()]),
            ];
        }
        return $this->render('jury/executables.html.twig', [
            'executables' => $executables_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 3 : 0,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/add", name="jury_executable_add")
     * @IsGranted("ROLE_ADMIN")
     */
    public function addAction(Request $request) : Response
    {
        $data = [];
        $form = $this->createForm(ExecutableUploadType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()){
            $propertyFile = 'domjudge-executable.ini';
            $data         = $form->getData();
            /** @var UploadedFile[] $archives */
            $archives = $data['archives'];
            $id       = null;
            foreach ($archives as $archive) {
                $zip         = $this->dj->openZipFile($archive->getRealPath());
                $filename    = $archive->getClientOriginalName();
                $id          = substr($filename, 0, strlen($filename) - strlen(".zip"));
                if ( ! preg_match ('#^[a-z0-9_-]+$#i', $id) ) {
                    throw new InvalidArgumentException(sprintf("File base name '%s' must contain only alphanumerics", $id));
                }
                $description = $id;
                $type        = $data['type'];

                $propertyData = $zip->getFromName($propertyFile);
                if ($propertyData !== false) {
                    $ini_array = parse_ini_string($propertyData);
                } else {
                    $ini_array = [];
                }
                if (!empty($ini_array)) {
                    $id          = $ini_array['execid'];
                    $description = $ini_array['description'];
                    $type        = $ini_array['type'];
                }

                $immutableExecutable = $this->dj->createImmutableExecutable($zip, $propertyFile);
                $executable = new Executable();
                $executable
                    ->setExecid($id)
                    ->setDescription($description)
                    ->setType($type)
                    ->setImmutableExecutable($immutableExecutable);
                $this->em->persist($executable);

                $zip->close();

                $this->dj->auditlog('executable', $id, 'upload zip', $archive->getClientOriginalName());
            }

            $this->em->flush();

            if (count($archives) === 1) {
                return $this->redirectToRoute('jury_executable', ['execId' => $id]);
            } else {
                return $this->redirectToRoute('jury_executables');
            }
        }

        return $this->render('jury/executable_add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{execId}", name="jury_executable")
     * @throws Exception
     */
    public function viewAction(Request $request, string $execId) : Response
    {
        /** @var Executable $executable */
        $executable = $this->em->getRepository(Executable::class)->find($execId);
        if (!$executable) {
            throw new NotFoundHttpException(sprintf('Executable with ID %s not found', $execId));
        }

        return $this->render('jury/executable.html.twig', [
            'executable' => $executable,
            'default_compare' => (string)$this->config->get('default_compare'),
            'default_run' => (string)$this->config->get('default_run'),
        ]);
    }

    /**
     * @Route("/{execId}/content", name="jury_executable_content")
     */
    public function contentAction(string $execId) : Response
    {
        /** @var Executable $executable */
        $executable = $this->em->getRepository(Executable::class)->find($execId);
        if (!$executable) {
            throw new NotFoundHttpException(sprintf('Executable with ID %s not found', $execId));
        }

        return $this->render('jury/executable_content.html.twig', $this->dataForEditor($executable));
    }

    /**
     * @Route("/{execId}/download", name="jury_executable_download")
     */
    public function downloadAction(string $execId) : Response
    {
        /** @var Executable $executable */
        $executable = $this->em->getRepository(Executable::class)->find($execId);
        if (!$executable) {
            throw new NotFoundHttpException(sprintf('Executable with ID %s not found', $execId));
        }

        $zipFileContent = $executable->getZipfileContent($this->dj->getDomjudgeTmpDir());
        $filename = sprintf('%s.zip', $executable->getExecid());

        return Utils::streamAsBinaryFile($zipFileContent, $filename, 'zip');
    }

    /**
     * @Route("/{execId}/download/{index}", name="jury_executable_download_single")
     */
    public function downloadSingleAction(string $execId, int $index) : Response
    {
        /** @var Executable $executable */
        $executable = $this->em->getRepository(Executable::class)->find($execId);
        if (!$executable) {
            throw new NotFoundHttpException(sprintf('Executable with ID %s not found', $execId));
        }

        /** @var ExecutableFile[] $files */
        $files = array_values($executable->getImmutableExecutable()->getFiles()->toArray());
        foreach ($files as $file) {
            if ($file->getRank() == $index) {
                return Utils::streamAsBinaryFile($file->getFileContent(), $file->getFilename());
            }
        }

        throw new NotFoundHttpException(sprintf('No file with index %d found', $index));
    }

    /**
     * @Route("/{execId}/edit", name="jury_executable_edit")
     * @IsGranted("ROLE_ADMIN")
     * @throws Exception
     */
    public function editAction(Request $request, string $execId) : Response
    {
        /** @var Executable $executable */
        $executable = $this->em->getRepository(Executable::class)->find($execId);
        if (!$executable) {
            throw new NotFoundHttpException(sprintf('Executable with ID %s not found', $execId));
        }

        $form = $this->createForm(ExecutableType::class, $executable);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $executable,
                              $executable->getExecid(), false);
            return $this->redirect($this->generateUrl(
                'jury_executable',
                ['execId' => $executable->getExecid()]
            ));
        }

        $data       = [];
        $uploadForm = $this->createFormBuilder($data)
            ->add('archive', FileType::class, [
                'required' => true,
                'attr' => [
                    'accept' => 'application/zip',
                ],
                'label' => 'Upload archive'
            ])
            ->add('upload', SubmitType::class)
            ->getForm();

        $uploadForm->handleRequest($request);

        if ($this->isGranted('ROLE_ADMIN') && $uploadForm->isSubmitted() && $uploadForm->isValid()) {
            $data = $uploadForm->getData();
            /** @var UploadedFile $archive */
            $archive = $data['archive'];
            $zip = $this->dj->openZipFile($archive->getRealPath());
            $executable->setImmutableExecutable(
                $this->dj->createImmutableExecutable($zip)
            );
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $executable,
                              $executable->getExecid(), false);
            return $this->redirectToRoute('jury_executable', ['execId' => $executable->getExecid()]);
        }

        return $this->render('jury/executable_edit.html.twig', [
            'executable' => $executable,
            'form' => $form->createView(),
            'uploadForm' => $uploadForm->createView(),
        ]);
    }

    /**
     * @Route("/{execId}/delete", name="jury_executable_delete")
     * @IsGranted("ROLE_ADMIN")
     * @return Response
     */
    public function deleteAction(Request $request, string $execId) : Response
    {
        /** @var Executable $executable */
        $executable = $this->em->getRepository(Executable::class)->find($execId);
        if (!$executable) {
            throw new NotFoundHttpException(sprintf('Executable with ID %s not found', $execId));
        }

        return $this->deleteEntity($request, $this->em, $this->dj, $this->eventLogService, $this->kernel, $executable,
                                   $executable->getDescription(), $this->generateUrl('jury_executables'));
    }

    /**
     * @Route("/{execId}/edit-files", name="jury_executable_edit_files")
     * @IsGranted("ROLE_ADMIN")
     */
    public function editFilesAction(Request $request, string $execId) : Response
    {
        /** @var Executable $executable */
        $executable = $this->em->getRepository(Executable::class)->find($execId);
        if (!$executable) {
            throw new NotFoundHttpException(sprintf('Executable with ID %s not found', $execId));
        }

        $editorData = $this->dataForEditor($executable);
        $data       = [];
        foreach ($editorData['files'] as $idx => $content) {
            $data['source' . $idx] = $content;
        }

        $formBuilder = $this->createFormBuilder($data)
            ->add('submit', SubmitType::class, ['label' => 'Save files']);

        foreach ($editorData['files'] as $idx => $content) {
            $formBuilder->add('source' . $idx, TextareaType::class);
        }

        $form = $formBuilder->getForm();

        // Handle the form if it is submitted
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            $immutableExecutable = new ImmutableExecutable();
            $this->em->persist($immutableExecutable);

            foreach ($editorData['filenames'] as $idx => $filename) {
                $newContent = str_replace("\r\n", "\n", $submittedData['source' . $idx]);

                $executableFile = new ExecutableFile();
                $executableFile
                    ->setRank($idx)
                    ->setImmutableExecutable($immutableExecutable)
                    ->setIsExecutable($editorData['executableBits'][$idx])
                    ->setFilename($filename)
                    ->setFileContent($newContent);
                $this->em->persist($executableFile);
            }

            $executable->setImmutableExecutable($immutableExecutable);
            $this->em->flush();
            $this->dj->auditlog('executable', $executable->getExecid(), 'updated');

            return $this->redirectToRoute('jury_executable', ['execId' => $executable->getExecid()]);
        }

        return $this->render('jury/executable_edit_content.html.twig', array_merge($editorData, [
            'form' => $form->createView(),
            'selected' => $request->query->get('index'),
        ]));
    }

    /**
     * Get the data to use for the executable editor
     */
    protected function dataForEditor(Executable $executable) : array
    {
        $immutable_executable = $executable->getImmutableExecutable();

        $filenames     = [];
        $file_contents = [];
        $aceFilenames  = [];
        $skippedBinary = [];
        $executableBits = [];
        foreach ($immutable_executable->getFiles() as $file) {
            /** @var ExecutableFile $file */
            $filename = $file->getFilename();
            $content = $file->getFileContent();
            if (!mb_check_encoding($content, 'ASCII')) {
                $skippedBinary[] = $filename;
                continue; // skip binary files
            }
            $filenames[] = $filename;
            $file_contents[] = $content;
            $executableBits[] = $file->isExecutable();

            if (strpos($filename, '.') !== false) {
                $aceFilenames[] = $filename;
            } else {
                list($firstLine) = explode("\n", $content, 2);
                // If the file does not contain a dot, see if we have a shebang which we can use as filename
                if (preg_match('/^#!.*\/([^\/]+)$/', $firstLine, $matches)) {
                    $aceFilenames[] = sprintf('dummy.%s', $matches[1]);
                } else {
                    $aceFilenames[] = $filename;
                }
            }
        }

        return [
            'executable' => $executable,
            'skippedBinary' => $skippedBinary,
            'filenames' => $filenames,
            'aceFilenames' => $aceFilenames,
            'files' => $file_contents,
            'executableBits' => $executableBits,
        ];
    }
}
