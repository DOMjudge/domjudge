<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\ContestProblem;
use App\Entity\Executable;
use App\Entity\ExecutableFile;
use App\Entity\ImmutableExecutable;
use App\Form\Type\ExecutableUploadType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException as PHPInvalidArgumentException;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_JURY')]
#[Route(path: '/jury/executables')]
class ExecutableController extends BaseController
{
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        KernelInterface $kernel,
        protected readonly EventLogService $eventLogService,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    #[Route(path: '', name: 'jury_executables')]
    public function indexAction(Request $request): Response
    {
        $executables_tables_used = [];
        $executables_tables_unused = [];
        $data = [];
        $form = $this->createForm(ExecutableUploadType::class, $data);
        $form->handleRequest($request);

        $em = $this->em;
        /** @var Executable[] $executables */
        $executables      = $em->createQueryBuilder()
            ->select('e as executable, e.execid as execid')
            ->from(Executable::class, 'e')
            ->addOrderBy('e.type', 'ASC')
            ->addOrderBy('e.execid', 'ASC')
            ->getQuery()->getResult();
        $executables      = array_column($executables, 'executable', 'execid');
        $table_fields     = [
            'icon' => ['title' => 'type', 'sort' => false],
            'execid' => ['title' => 'ID', 'sort' => true,],
            'type' => ['title' => 'type', 'sort' => true,],
            'badges' => ['title' => 'problems', 'sort' => false],
            'description' => ['title' => 'description', 'sort' => true,],
        ];

        $propertyAccessor  = PropertyAccess::createPropertyAccessor();
        $configScripts = [];
        foreach (['compare', 'run', 'full_debug'] as $config_script) {
            try {
                $configScripts[] = (string)$this->config->get('default_' . $config_script);
            } catch (PHPInvalidArgumentException $e) {
                // If not found this is an older database, as we only use this for visual changes ignore this error;
            }
        }

        $contestProblemsWithExecutables = [];
        $executablesWithContestProblems = [];
        if ($this->dj->getCurrentContest()) {
            $contestProblemsWithExecutables = $em->createQueryBuilder()
                ->select('cp', 'p', 'ecomp')
                ->from(ContestProblem::class, 'cp')
                ->where('cp.contest = :contest')
                ->setParameter('contest', $this->dj->getCurrentContest())
                ->join('cp.problem', 'p')
                ->leftJoin('p.compare_executable', 'ecomp')
                ->leftJoin('p.run_executable', 'erun')
                ->andWhere('ecomp IS NOT NULL OR erun IS NOT NULL')
                ->getQuery()->getResult();
            $executablesWithContestProblems = $em->createQueryBuilder()
                ->select('e')
                ->from(Executable::class, 'e')
                ->leftJoin('e.problems_compare', 'pcomp')
                ->leftJoin('e.problems_run', 'prun')
                ->where('pcomp IS NOT NULL OR prun IS NOT NULL')
                ->leftJoin('pcomp.contest_problems', 'cpcomp')
                ->leftJoin('prun.contest_problems', 'cprun')
                ->andWhere('cprun.contest = :contest OR cpcomp.contest = :contest')
                ->setParameter('contest', $this->dj->getCurrentContest())
                ->getQuery()->getResult();
        }

        foreach ($executables as $e) {
            $badges = [];
            if (in_array($e, $executablesWithContestProblems)) {
                foreach (array_merge($e->getProblemsRun()->toArray(), $e->getProblemsCompare()->toArray()) as $execProblem) {
                    $execContestProblems = $execProblem->getContestProblems();
                    foreach ($contestProblemsWithExecutables as $cp) {
                        if ($execContestProblems->contains($cp)) {
                            $badges[] = $cp;
                        }
                    }
                }
            }
            sort($badges);

            $execdata    = [];
            $execactions = [];
            // Get whatever fields we can from the team object itself.
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($e, $k)) {
                    $execdata[$k] = ['value' => $propertyAccessor->getValue($e, $k)];
                }
            }
            $execdata['execid']['cssclass'] = 'execid';
            $type = $execdata['type']['value'];
            switch ($type) {
                case 'compare':
                    $execdata['icon']['icon'] = 'code-compare';
                    break;
                case 'compile':
                    $execdata['icon']['icon'] = 'language';
                    break;
                case 'debug':
                    $execdata['icon']['icon'] = 'bug';
                    break;
                case 'run':
                    $execdata['icon']['icon'] = 'person-running';
                    break;
                default:
                    $execdata['icon']['icon'] = 'question';
            }
            $execdata['badges']['value'] = $badges;

            if ($this->isGranted('ROLE_ADMIN')) {
                $execactions[] = [
                    'icon' => 'edit',
                    'title' => 'edit this executable',
                    'link' => $this->generateUrl('jury_executable', [
                        'execId' => $e->getExecid(),
                    ]),
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

            if ($e->checkUsed($configScripts)) {
                $executables_tables_used[] = [
                    'data' => $execdata,
                    'actions' => $execactions,
                    'link' => $this->generateUrl('jury_executable', ['execId' => $e->getExecid()]),
                ];
            } else {
                $executables_tables_unused[] = [
                    'data' => $execdata,
                    'actions' => $execactions,
                    'link' => $this->generateUrl('jury_executable', ['execId' => $e->getExecid()]),
                    'cssclass' => 'disabled',
                ];
            }
        }
        // This is replaced with the icon.
        unset($table_fields['type']);

        return $this->render('jury/executables.html.twig', [
            'executables_used' => $executables_tables_used,
            'executables_unused' => $executables_tables_unused,
            'table_fields' => $table_fields,
            'form' => $form,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/add', name: 'jury_executable_add')]
    public function addAction(Request $request): Response
    {
        $data = [];
        $form = $this->createForm(ExecutableUploadType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $propertyFile = 'domjudge-executable.ini';
            $data         = $form->getData();
            /** @var UploadedFile[] $archives */
            $archives = $data['archives'];
            $id       = null;
            foreach ($archives as $archive) {
                $zip         = $this->dj->openZipFile($archive->getRealPath());
                $filename    = $archive->getClientOriginalName();
                $id          = substr($filename, 0, strlen($filename) - strlen(".zip"));
                if (! preg_match('#^[a-z0-9_-]+$#i', $id)) {
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

                $immutableExecutable = $this->dj->createImmutableExecutable($zip);
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
            'form' => $form,
        ]);
    }

    #[Route(path: '/{execId}', name: 'jury_executable')]
    public function viewAction(
        Request $request,
        string $execId,
        #[MapQueryParameter]
        ?int $index = null
    ): Response {
        $executable = $this->em->getRepository(Executable::class)->find($execId);
        if (!$executable) {
            throw new NotFoundHttpException(sprintf('Executable with ID %s not found', $execId));
        }

        $editorData = $this->dataForEditor($executable);
        $data       = [];
        foreach ($editorData['files'] as $idx => $content) {
            $data['source' . $idx] = $content;
        }

        $formBuilder = $this->createFormBuilder($data);
        if ($this->isGranted('ROLE_ADMIN')) {
            $formBuilder->add('submit', SubmitType::class, ['label' => 'Save files']);
        }

        foreach ($editorData['files'] as $idx => $content) {
            $formBuilder->add('source' . $idx, TextareaType::class);
        }

        $form = $formBuilder->getForm();

        // Handle the form if it is submitted.
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->isGranted('ROLE_ADMIN')) {
                $this->addFlash('danger', 'You must have the admin role to submit changes.');
                return $this->redirectToRoute('jury_executable', ['execId' => $executable->getExecid()]);
            }
            $submittedData = $form->getData();

            $files = [];
            foreach ($editorData['filenames'] as $idx => $filename) {
                $newContent = str_replace("\r\n", "\n", $submittedData['source' . $idx]);
                if (!str_ends_with($newContent, "\n")) {
                    // Ace swallows the newline at the end of file. Let's re-add it like most editors do.
                    $newContent .= "\n";
                }

                $executableFile = new ExecutableFile();
                $executableFile
                    ->setRank($idx)
                    ->setIsExecutable($editorData['executableBits'][$idx])
                    ->setFilename($filename)
                    ->setFileContent($newContent);
                $this->em->persist($executableFile);
                $files[] = $executableFile;
            }
            $offset = count($files);
            foreach ($editorData['skippedBinary'] as $idx => $skippedBinaryData) {
                $origExecutableFile = $this->em->getRepository(ExecutableFile::class)->find($skippedBinaryData['execfileid']);
                $executableFile = new ExecutableFile();
                $executableFile
                    ->setRank($idx + $offset)
                    ->setIsExecutable($origExecutableFile->isExecutable())
                    ->setFilename($origExecutableFile->getFilename())
                    ->setFileContent($origExecutableFile->getFileContent());
                $this->em->persist($executableFile);
                $files[] = $executableFile;
            }

            $immutableExecutable = new ImmutableExecutable($files);
            $this->em->persist($immutableExecutable);
            $executable->setImmutableExecutable($immutableExecutable);
            $this->em->flush();
            $this->dj->auditlog('executable', $executable->getExecid(), 'updated');

            return $this->redirectToRoute('jury_executable', ['execId' => $executable->getExecid()]);
        }

        $data       = [];
        $uploadForm = $this->createFormBuilder($data)
            ->add('archive', FileType::class, [
                'required' => true,
                'attr' => [
                    'accept' => 'application/zip',
                ],
                'label' => 'Replace executable with new ZIP'
            ])
            ->add('upload', SubmitType::class, ['label' => 'Replace'])
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
            $this->saveEntity($executable, $executable->getExecid(), false);
            return $this->redirectToRoute('jury_executable', ['execId' => $executable->getExecid()]);
        }

        return $this->render('jury/executable.html.twig', array_merge($editorData, [
            'form' => $form->createView(),
            'uploadForm' => $uploadForm->createView(),
            'selected' => $index,
            'executable' => $executable,
            'default_compare' => (string)$this->config->get('default_compare'),
            'default_run' => (string)$this->config->get('default_run'),
            'default full debug' => (string)$this->config->get('default_full_debug'),
        ]));
    }

    #[Route(path: '/{execId}/download', name: 'jury_executable_download')]
    public function downloadAction(string $execId): Response
    {
        $executable = $this->em->getRepository(Executable::class)->find($execId);
        if (!$executable) {
            throw new NotFoundHttpException(sprintf('Executable with ID %s not found', $execId));
        }

        $zipFileContent = $executable->getZipfileContent($this->dj->getDomjudgeTmpDir());
        $filename = sprintf('%s.zip', $executable->getExecid());

        return Utils::streamAsBinaryFile($zipFileContent, $filename, 'zip');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/{execId}/delete/{rankToDelete}', name: 'jury_executable_delete_single')]
    public function deleteSingleAction(Request $request, string $execId, int $rankToDelete): Response
    {
        $executable = $this->em->getRepository(Executable::class)->find($execId);
        if (!$executable) {
            throw new NotFoundHttpException(sprintf('Executable with ID %s not found.', $execId));
        }

        /** @var ExecutableFile[] $files */
        $files = array_values($executable->getImmutableExecutable()->getFiles()->toArray());
        $fileToDelete = null;
        foreach ($files as $fileToDelete) {
            if ($fileToDelete->getRank() == $rankToDelete) {
                break;
            }
        }
        if (!$fileToDelete) {
            throw new NotFoundHttpException(sprintf('File with rank %d not found in executable with ID %s.', $rankToDelete, $execId));
        }

        if ($request->isMethod('GET')) {
            $data = [
                'type' => 'ExecutableFile',
                'primaryKey' => $execId,
                'description' => $fileToDelete->getFilename(),
                'messages' => [],
                'isError' => false,
                'showModalSubmit' => true,
                'modalUrl' => $request->getRequestUri(),
                'redirectUrl' => $this->generateUrl('jury_executable', ['execId' => $execId]),
            ];
            if ($request->isXmlHttpRequest()) {
                return $this->render('jury/delete_modal.html.twig', $data);
            }

            return $this->render('jury/delete.html.twig', $data);
        } else {
            // Create a copy of all files except $file
            $files = [];
            /** @var ExecutableFile $file */
            foreach ($executable->getImmutableExecutable()->getFiles() as $file) {
                if ($file->getRank() == $rankToDelete) {
                    continue;
                }

                $executableFile = new ExecutableFile();
                $executableFile
                    ->setRank($file->getRank())
                    ->setIsExecutable($file->isExecutable())
                    ->setFilename($file->getFilename())
                    ->setFileContent($file->getFileContent());
                $this->em->persist($executableFile);
                $files[] = $executableFile;
            }
            $immutableExecutable = new ImmutableExecutable($files);
            $this->em->persist($immutableExecutable);
            $executable->setImmutableExecutable($immutableExecutable);
            $this->em->flush();
            $redirectUrl = $this->generateUrl('jury_executable', ['execId' => $execId]);
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['url' => $redirectUrl]);
            }
            return $this->redirect($redirectUrl);
        }
    }

    #[Route(path: '/{execId}/download/{rank}', name: 'jury_executable_download_single')]
    public function downloadSingleAction(string $execId, int $rank): Response
    {
        $executable = $this->em->getRepository(Executable::class)->find($execId);
        if (!$executable) {
            throw new NotFoundHttpException(sprintf('Executable with ID %s not found.', $execId));
        }

        /** @var ExecutableFile[] $files */
        $files = array_values($executable->getImmutableExecutable()->getFiles()->toArray());
        foreach ($files as $file) {
            if ($file->getRank() == $rank) {
                return Utils::streamAsBinaryFile($file->getFileContent(), $file->getFilename());
            }
        }

        throw new NotFoundHttpException(sprintf('No file with rank %d found.', $rank));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/{execId}/delete', name: 'jury_executable_delete')]
    public function deleteAction(Request $request, string $execId): Response
    {
        $executable = $this->em->getRepository(Executable::class)->find($execId);
        if (!$executable) {
            throw new NotFoundHttpException(sprintf('Executable with ID %s not found', $execId));
        }

        return $this->deleteEntities($request, [$executable], $this->generateUrl('jury_executables'));
    }

    /**
     * Get the data to use for the executable editor.
     *
     * @return array{'executable': Executable, 'filenames': string[],
     *               'skippedBinary': array<array{filename: string, execfileid: int}>,
     *               'aceFilenames': string[], 'ranks': int[],
     *               'files': string[], 'executableBits': bool[]}
     */
    protected function dataForEditor(Executable $executable): array
    {
        $immutable_executable = $executable->getImmutableExecutable();

        $filenames      = [];
        $file_contents  = [];
        $aceFilenames   = [];
        $skippedBinary  = [];
        $executableBits = [];
        $ranks          = [];

        $files = $immutable_executable->getFiles()->toArray();
        usort($files, fn($a, $b) => $a->getFilename() <=> $b->getFilename());
        foreach ($files as $file) {
            /** @var ExecutableFile $file */
            $filename = $file->getFilename();
            $content = $file->getFileContent();
            $rank = $file->getRank();
            if (!mb_detect_encoding($content, null, true)) {
                $skippedBinary[] = [
                    'filename' => $filename,
                    'execfileid' => $file->getExecFileId(),
                ];
                continue; // Skip binary files.
            }
            $filenames[] = $filename;
            $ranks[] = $rank;
            $file_contents[] = $content;
            $executableBits[] = $file->isExecutable();
            $aceFilenames[] = $this->getAceFilename($filename, $content);
        }

        return [
            'executable' => $executable,
            'skippedBinary' => $skippedBinary,
            'filenames' => $filenames,
            'aceFilenames' => $aceFilenames,
            'ranks' => $ranks,
            'files' => $file_contents,
            'executableBits' => $executableBits,
        ];
    }

    private function getAceFilename(string $filename, string $content): string
    {
        if (!str_contains($filename, '.')) {
            // If the file does not contain a dot, see if we have a shebang which we can use as filename.
            // We do this to hint the ACE editor to use a specific language.
            [$firstLine] = explode("\n", $content, 2);
            if (preg_match('/^#!.*\/([^\/]+)$/', $firstLine, $matches)) {
                return sprintf('temp.%s', $matches[1]);
            }
        }
        return $filename;
    }
}
