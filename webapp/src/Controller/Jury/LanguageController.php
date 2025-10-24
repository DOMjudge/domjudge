<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\DataTransferObject\SubmissionRestriction;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Submission;
use App\Form\Type\LanguageType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\SubmissionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_JURY')]
#[Route(path: '/jury/languages')]
class LanguageController extends BaseController
{
    use JudgeRemainingTrait;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        KernelInterface $kernel,
        protected readonly EventLogService $eventLogService,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    #[Route(path: '', name: 'jury_languages')]
    public function indexAction(): Response
    {
        $em = $this->em;
        /** @var Language[] $languages */
        $languages    = $em->createQueryBuilder()
            ->select('lang')
            ->from(Language::class, 'lang')
            ->orderBy('lang.name', 'ASC')
            ->getQuery()->getResult();
        $table_fields = [
            'externalid' => ['title' => 'ID', 'sort' => true],
            'name' => ['title' => 'name', 'sort' => true, 'default_sort' => true],
            'entrypoint' => ['title' => 'entry point', 'sort' => true],
            'allowjudge' => ['title' => 'allow judge', 'sort' => true],
            'timefactor' => ['title' => 'timefactor', 'sort' => true],
            'extensions' => ['title' => 'extensions', 'sort' => true],
            'executable' => ['title' => 'executable', 'sort' => true],
        ];

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $enabled_languages  = [];
        $disabled_languages  = [];
        foreach ($languages as $lang) {
            $langdata    = [];
            $langactions = [];
            // Get whatever fields we can from the language object itself.
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($lang, $k)) {
                    $langdata[$k] = ['value' => $propertyAccessor->getValue($lang, $k)];
                }
            }

            if ($this->isGranted('ROLE_ADMIN')) {
                $langactions[] = [
                    'icon' => 'edit',
                    'title' => 'edit this language',
                    'link' => $this->generateUrl('jury_language_edit', [
                        'langId' => $lang->getExternalid()
                    ])
                ];
                $langactions[] = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this language',
                    'link' => $this->generateUrl('jury_language_delete', [
                        'langId' => $lang->getExternalid(),
                    ]),
                    'ajaxModal' => true,
                ];
            }

            $executable = $lang->getCompileExecutable();

            $allowJudgeOptions = [
                'toggle_partial' => 'language_toggle.html.twig',
                'partial_arguments' => [
                    'path' => 'jury_language_toggle_judge',
                    'language' => $lang,
                    'value' => $lang->getAllowJudge(),
                ],
            ];

            if (!$lang->getAllowJudge()) {
                $allowJudgeOptions['cssclass'] = 'text-danger font-weight-bold';
            }

            // Merge in the rest of the data.
            $langdata = array_merge($langdata, [
                'entrypoint' => ['value' => $lang->getRequireEntryPoint() ? 'yes' : 'no'],
                'extensions' => ['value' => implode(', ', $lang->getExtensions())],
                'allowjudge' => $allowJudgeOptions,
                'executable' => [
                    'value' => $executable === null ? '-' : $executable->getShortDescription(),
                    'link' => $executable === null ? null : $this->generateUrl('jury_executable', [
                        'execId' => $executable->getExecid()
                        ]),
                    'showlink' => true,
                    ],
            ]);

            if ($lang->getAllowSubmit()) {
                $enabled_languages[] = [
                    'data' => $langdata,
                    'actions' => $langactions,
                    'link' => $this->generateUrl('jury_language', ['langId' => $lang->getExternalid()]),
                    'cssclass' => '',
                ];
            } else {
                $disabled_languages[] = [
                    'data' => $langdata,
                    'actions' => $langactions,
                    'link' => $this->generateUrl('jury_language', ['langId' => $lang->getExternalid()]),
                    'cssclass' => 'disabled',
                ];
            }
        }
        return $this->render('jury/languages.html.twig', [
            'enabled_languages' => $enabled_languages,
            'disabled_languages' => $disabled_languages,
            'table_fields' => $table_fields,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/add', name: 'jury_language_add', priority: 1)]
    public function addAction(Request $request): Response
    {
        $language = new Language();

        $form = $this->createForm(LanguageType::class, $language);

        $form->handleRequest($request);

        if ($response = $this->processAddFormForExternalIdEntity(
            $form, $language,
            fn() => $this->generateUrl('jury_language', ['langId' => $language->getExternalid()]),
            function () use ($language) {
                // Normalize extensions
                if ($language->getExtensions()) {
                    $language->setExtensions(array_values($language->getExtensions()));
                }
                $this->em->persist($language);
                $this->saveEntity($language,
                    $language->getLangid(), true);

                return null;
            }
        )) {
            return $response;
        }

        return $this->render('jury/language_add.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    #[Route(path: '/{langId}', name: 'jury_language')]
    public function viewAction(Request $request, SubmissionService $submissionService, string $langId): Response
    {
        $language = $this->em->getRepository(Language::class)->findByExternalId($langId);
        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s not found', $langId));
        }

        /** @var Submission[] $submissions */
        [$submissions, $submissionCounts] = $submissionService->getSubmissionList(
            $this->dj->getCurrentContests(honorCookie: true),
            new SubmissionRestriction(languageId: $language->getLangid()),
            page: $request->query->getInt('page', 1),
        );

        $data = [
            'language' => $language,
            'previousNext' => $this->getPreviousAndNextObjectIds(
                Language::class,
                $language->getExternalid(),
            ),
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'showContest' => count($this->dj->getCurrentContests(honorCookie: true)) > 1,
            'showExternalResult' => $this->dj->shadowMode(),
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_language', ['langId' => $language->getExternalid()]),
                'ajax' => true,
            ],
        ];

        // For ajax requests, only return the submission list partial.
        if ($request->isXmlHttpRequest()) {
            $data['showTestcases'] = false;
            return $this->render('jury/partials/submission_list.html.twig', $data);
        }

        return $this->render('jury/language.html.twig', $data);
    }

    #[Route(path: '/{langId}/toggle-submit', name: 'jury_language_toggle_submit')]
    public function toggleSubmitAction(Request $request, string $langId): Response
    {
        $language = $this->em->getRepository(Language::class)->findByExternalId($langId);
        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s not found', $langId));
        }

        $language->setAllowSubmit($request->request->getBoolean('value'));
        $this->em->flush();

        $this->dj->auditlog('language', $language->getExternalid(), 'set allow submit',
                                         $request->request->getBoolean('value') ? 'yes' : 'no');
        return $this->redirectToRoute('jury_language', ['langId' => $langId]);
    }

    #[Route(path: '/{langId}/toggle-judge', name: 'jury_language_toggle_judge')]
    public function toggleJudgeAction(
        RouterInterface $router,
        Request $request,
        string $langId
    ): Response {
        $language = $this->em->getRepository(Language::class)->findByExternalId($langId);
        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s not found', $langId));
        }

        $enabled = $request->request->getBoolean('value');
        $language->setAllowJudge($enabled);
        $this->em->flush();

        if ($enabled) {
            $this->dj->unblockJudgeTasksForLanguage($language->getLangid());
        }

        $this->dj->auditlog('language', $language->getExternalid(), 'set allow judge',
                                         $request->request->getBoolean('value') ? 'yes' : 'no');
        return $this->redirectToLocalReferrer(
            $router,
            $request,
            $this->generateUrl('jury_language', ['langId' => $langId])
        );
    }

    #[Route(path: '/{langId}/toggle-filter-compiler-flags', name: 'jury_language_toggle_filter_compiler_files')]
    public function toggleFilterCompilerFlagsAction(Request $request, string $langId): Response
    {
        $language = $this->em->getRepository(Language::class)->findByExternalId($langId);
        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s not found', $langId));
        }

        $enabled = $request->request->getBoolean('value');
        $language->setFilterCompilerFiles($enabled);
        $this->em->flush();

        $this->dj->auditlog('language', $language->getExternalid(), 'set filter compiler flags',
            $request->request->getBoolean('value') ? 'yes' : 'no');
        return $this->redirectToRoute('jury_language', ['langId' => $langId]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/{langId}/edit', name: 'jury_language_edit')]
    public function editAction(Request $request, string $langId): Response
    {
        $language = $this->em->getRepository(Language::class)->findByExternalId($langId);
        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s not found', $langId));
        }

        $form = $this->createForm(LanguageType::class, $language);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Normalize extensions.
            if ($language->getExtensions()) {
                $language->setExtensions(array_values($language->getExtensions()));
            }
            $this->saveEntity($language, $language->getLangid(), false);
            if ($language->getAllowJudge()) {
                $this->dj->unblockJudgeTasksForLanguage($language->getLangid());
            }
            return $this->redirectToRoute('jury_language', ['langId' => $langId]);
        }

        return $this->render('jury/language_edit.html.twig', [
            'language' => $language,
            'form' => $form,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/{langId}/delete', name: 'jury_language_delete')]
    public function deleteAction(Request $request, string $langId): Response
    {
        $language = $this->em->getRepository(Language::class)->findByExternalId($langId);
        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s not found', $langId));
        }

        return $this->deleteEntities($request, [$language], $this->generateUrl('jury_languages')
        );
    }

    #[Route(path: '/{langId}/request-remaining', name: 'jury_language_request_remaining')]
    public function requestRemainingRunsWholeLanguageAction(string $langId): RedirectResponse
    {
        $language = $this->em->getRepository(Language::class)->findByExternalId($langId);
        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s not found', $langId));
        }
        $contestId = $this->dj->getCurrentContest()->getExternalid();
        $this->judgeRemaining(contestId: $contestId, langId: $langId);
        return $this->redirectToRoute('jury_language', ['langId' => $langId]);
    }
}
