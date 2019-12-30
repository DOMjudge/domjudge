<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Executable;
use App\Form\Type\ExecutableType;
use App\Form\Type\ExecutableUploadType;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

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
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * ExecutableController constructor.
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param KernelInterface        $kernel
     * @param EventLogService        $eventLogService
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        KernelInterface $kernel,
        EventLogService $eventLogService
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
        $this->kernel          = $kernel;
        $this->eventLogService = $eventLogService;
    }

    /**
     * @Route("", name="jury_executables")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request)
    {
        $data = [];
        $form = $this->createForm(ExecutableUploadType::class, $data);
        $form->handleRequest($request);

        if ($this->isGranted('ROLE_ADMIN') && $form->isSubmitted() && $form->isValid()) {
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

                $executable = new Executable();
                $executable
                    ->setExecid($id)
                    ->setDescription($description)
                    ->setType($type)
                    ->setMd5sum(md5_file($archive->getRealPath()))
                    ->setZipfile(file_get_contents($archive->getRealPath()));
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

        $em = $this->em;
        /** @var Executable[] $executables */
        $executables      = $em->createQueryBuilder()
            ->select('e as executable, e.execid as execid, length(e.zipfile) as size')
            ->from(Executable::class, 'e')
            ->orderBy('e.execid', 'ASC')
            ->getQuery()->getResult();
        $executable_sizes = array_column($executables, 'size', 'execid');
        $executables      = array_column($executables, 'executable', 'execid');
        $table_fields     = [
            'execid' => ['title' => 'ID', 'sort' => true,],
            'type' => ['title' => 'type', 'sort' => true,],
            'description' => ['title' => 'description', 'sort' => true, 'default_sort' => true],
            'size' => ['title' => 'size', 'sort' => true,],
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

            $execdata['md5sum']['cssclass'] = 'text-monospace small';
            $execdata                       = array_merge($execdata, [
                'size' => [
                    'value' => Utils::printsize((int)$executable_sizes[$e->getExecid()]),
                    'sortvalue' => (int)$executable_sizes[$e->getExecid()]
                ],
            ]);
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
     * @Route("/{execId}", name="jury_executable")
     * @param Request $request
     * @param string  $execId
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function viewAction(Request $request, string $execId)
    {
        /** @var Executable $executable */
        $executable = $this->em->getRepository(Executable::class)->find($execId);
        if (!$executable) {
            throw new NotFoundHttpException(sprintf('Executable with ID %s not found', $execId));
        }

        return $this->render('jury/executable.html.twig', [
            'executable' => $executable,
            'default_compare' => (string)$this->dj->dbconfig_get('default_compare'),
            'default_run' => (string)$this->dj->dbconfig_get('default_run'),
        ]);
    }

    /**
     * @Route("/{execId}/content", name="jury_executable_content")
     * @param string $execId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function contentAction(string $execId)
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
     * @param string $execId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadAction(string $execId)
    {
        /** @var Executable $executable */
        $executable = $this->em->getRepository(Executable::class)->find($execId);
        if (!$executable) {
            throw new NotFoundHttpException(sprintf('Executable with ID %s not found', $execId));
        }

        $zipFile     = stream_get_contents($executable->getZipfile());
        $zipFileSize = strlen($zipFile);
        $filename    = sprintf('%s.zip', $executable->getExecid());

        $response = new StreamedResponse();
        $response->setCallback(function () use ($zipFile) {
            echo $zipFile;
        });
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Content-Length', $zipFileSize);
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Connection', 'Keep-Alive');
        $response->headers->set('Accept-Ranges', 'bytes');

        return $response;
    }

    /**
     * @Route("/{execId}/download/{index}", name="jury_executable_download_single")
     * @param string $execId
     * @param int    $index
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadSingleAction(string $execId, int $index)
    {
        /** @var Executable $executable */
        $executable = $this->em->getRepository(Executable::class)->find($execId);
        if (!$executable) {
            throw new NotFoundHttpException(sprintf('Executable with ID %s not found', $execId));
        }

        if (!($tempzipFile = tempnam($this->dj->getDomjudgeTmpDir(), "/executable-"))) {
            throw new ServiceUnavailableHttpException(null, 'Failed to create temporary file');
        }
        if (file_put_contents($tempzipFile, stream_get_contents($executable->getZipfile())) === false) {
            throw new ServiceUnavailableHttpException(null, 'Failed to write zip file to temporary file');
        }

        $zip = $this->dj->openZipFile($tempzipFile);

        if ($index < 0 || $index >= $zip->numFiles) {
            throw new BadRequestHttpException(sprintf('File with index %d not found', $index));
        }

        $filename = basename($zip->getNameIndex($index));
        if ($filename[strlen($filename) - 1] == "/") {
            throw new BadRequestHttpException(sprintf('File with index %d is a directory', $index));
        }

        $content = $zip->getFromIndex($index);

        $response = new StreamedResponse();
        $response->setCallback(function () use ($content) {
            echo $content;
        });
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Content-Length', strlen($content));
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Connection', 'Keep-Alive');
        $response->headers->set('Accept-Ranges', 'bytes');

        return $response;
    }

    /**
     * @Route("/{execId}/edit", name="jury_executable_edit")
     * @IsGranted("ROLE_ADMIN")
     * @param Request $request
     * @param string  $execId
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function editAction(Request $request, string $execId)
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

            $executable
                ->setMd5sum(md5_file($archive->getRealPath()))
                ->setZipfile(file_get_contents($archive->getRealPath()));
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
     * @param Request $request
     * @param string  $execId
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function deleteAction(Request $request, string $execId)
    {
        /** @var Executable $executable */
        $executable = $this->em->getRepository(Executable::class)->find($execId);
        if (!$executable) {
            throw new NotFoundHttpException(sprintf('Executable with ID %s not found', $execId));
        }

        return $this->deleteEntity($request, $this->em, $this->dj, $this->kernel, $executable,
                                   $executable->getDescription(), $this->generateUrl('jury_executables'));
    }

    /**
     * @Route("/{execId}/edit-files", name="jury_executable_edit_files")
     * @IsGranted("ROLE_ADMIN")
     * @param Request $request
     * @param string  $execId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editFilesAction(Request $request, string $execId)
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
            ->add('submit', SubmitType::class);

        foreach ($editorData['files'] as $idx => $content) {
            $formBuilder->add('source' . $idx, TextareaType::class);
        }

        $form = $formBuilder->getForm();

        // Handle the form if it is submitted
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            if (!($tempzipFile = tempnam($this->dj->getDomjudgeTmpDir(), "/executable-"))) {
                throw new ServiceUnavailableHttpException(null, 'Failed to create temporary file');
            }
            if (file_put_contents($tempzipFile, $executable->getZipfile(true)) === false) {
                throw new ServiceUnavailableHttpException(null, 'Failed to write zip file to temporary file');
            }

            $zip = $this->dj->openZipFile($tempzipFile);
            foreach ($editorData['filenames'] as $idx => $filename) {
                $permission = $opsys = $attr = null;
                if ($zip->getExternalAttributesName($filename, $opsys, $attr) && $opsys === \ZipArchive::OPSYS_UNIX) {
                    $permission = $attr;
                }

                $newContent = $submittedData['source' . $idx];
                $zip->addFromString($filename, str_replace("\r\n", "\n", $newContent));

                if ($permission !== null) {
                    $zip->setExternalAttributesName($filename, $opsys, $permission);
                }
            }

            $zip->close();

            $executable
                ->setMd5sum(md5_file($tempzipFile))
                ->setZipfile(file_get_contents($tempzipFile));
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
     * @param Executable $executable
     * @return array
     */
    protected function dataForEditor(Executable $executable)
    {
        if (!($tempzipFile = tempnam($this->dj->getDomjudgeTmpDir(), "/executable-"))) {
            throw new ServiceUnavailableHttpException(null, 'Failed to create temporary file');
        }
        if (file_put_contents($tempzipFile, $executable->getZipfile(true)) === false) {
            throw new ServiceUnavailableHttpException(null, 'Failed to write zip file to temporary file');
        }

        $zip           = $this->dj->openZipFile($tempzipFile);
        $skippedBinary = [];
        $filenames     = [];
        $files         = [];
        $aceFilenames  = [];
        for ($idx = 0; $idx < $zip->numFiles; $idx++) {
            $filename = basename($zip->getNameIndex($idx));
            if ($filename[strlen($filename) - 1] == "/") {
                continue;
            }

            $content = $zip->getFromIndex($idx);
            if (!mb_check_encoding($content, 'ASCII')) {
                $skippedBinary[] = $filename;
                continue; // skip binary files
            }

            $filenames[] = $filename;
            $files[]     = $content;

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

        $zip->close();

        return [
            'executable' => $executable,
            'skippedBinary' => $skippedBinary,
            'filenames' => $filenames,
            'aceFilenames' => $aceFilenames,
            'files' => $files,
        ];
    }
}
