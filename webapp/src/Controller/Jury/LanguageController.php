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
use App\Twig\Attribute\AjaxTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Form\FormInterface;
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

    /**
     * @return array{
     *     enabled_languages: list<array{
     *         data: array<string, array<string, mixed>>,
     *         actions: list<array<string, string>>,
     *         link: string,
     *         cssclass: string
     *     }>,
     *     disabled_languages: list<array{
     *         data: array<string, array<string, mixed>>,
     *         actions: list<array<string, string>>,
     *         link: string,
     *         cssclass: string
     *     }>,
     *     table_fields: array<string, array<string, mixed>>
     * }
     */
    #[Route(path: '', name: 'jury_languages')]
    #[Template(template: 'jury/languages.html.twig')]
    public function indexAction(): array
    {
        $em = $this->em;
        /** @var Language[] $languages */
        $languages    = $em->createQueryBuilder()
            ->select('lang')
            ->from(Language::class, 'lang')
            ->orderBy('lang.name', 'ASC')
            ->getQuery()->getResult();
        $table_fields = [
            'langid' => ['title' => 'ID', 'sort' => true],
            'externalid' => ['title' => 'external ID', 'sort' => true],
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
                        'langId' => $lang->getLangid()
                    ])
                ];
                $langactions[] = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this language',
                    'link' => $this->generateUrl('jury_language_delete', [
                        'langId' => $lang->getLangid(),
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
                    'link' => $this->generateUrl('jury_language', ['langId' => $lang->getLangid()]),
                    'cssclass' => '',
                ];
            } else {
                $disabled_languages[] = [
                    'data' => $langdata,
                    'actions' => $langactions,
                    'link' => $this->generateUrl('jury_language', ['langId' => $lang->getLangid()]),
                    'cssclass' => 'disabled',
                ];
            }
        }
        return [
            'enabled_languages' => $enabled_languages,
            'disabled_languages' => $disabled_languages,
            'table_fields' => $table_fields,
        ];
    }

    // Note that the add action appears before the view action to make sure
    // /add is not seen as a language.
    /**
     * @return array{form: FormInterface}|Response
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/add', name: 'jury_language_add')]
    #[Template(template: 'jury/language_add.html.twig')]
    public function addAction(Request $request): array|Response
    {
        $language = new Language();

        $form = $this->createForm(LanguageType::class, $language);

        $form->handleRequest($request);

        if ($response = $this->processAddFormForExternalIdEntity(
            $form, $language,
            fn() => $this->generateUrl('jury_language', ['langId' => $language->getLangid()]),
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

        return [
            'form' => $form,
        ];
    }

    /**
     * @return array{
     *     language: Language,
     *     submissions: list<Submission>,
     *     submissionCounts: array<string, int>,
     *     showContest: bool,
     *     showExternalResult: bool,
     *     showTestcases?: bool,
     *     refresh: array{after: int, url: string, ajax: bool}
     * }
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    #[Route(path: '/{langId}', name: 'jury_language')]
    #[AjaxTemplate(
        normalTemplate: 'jury/language.html.twig',
        ajaxTemplate: 'jury/partials/submission_list.html.twig'
    )]
    public function viewAction(Request $request, SubmissionService $submissionService, string $langId): array
    {
        $language = $this->em->getRepository(Language::class)->find($langId);
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
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'showContest' => count($this->dj->getCurrentContests(honorCookie: true)) > 1,
            'showExternalResult' => $this->dj->shadowMode(),
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_language', ['langId' => $language->getLangid()]),
                'ajax' => true,
            ],
        ];

        // For ajax requests, only return the submission list partial.
        if ($request->isXmlHttpRequest()) {
            $data['showTestcases'] = false;
        }

        return $data;
    }

    #[Route(path: '/{langId}/toggle-submit', name: 'jury_language_toggle_submit')]
    public function toggleSubmitAction(Request $request, string $langId): Response
    {
        $language = $this->em->getRepository(Language::class)->find($langId);
        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s not found', $langId));
        }

        $language->setAllowSubmit($request->request->getBoolean('value'));
        $this->em->flush();

        $this->dj->auditlog('language', $langId, 'set allow submit',
                                         $request->request->getBoolean('value') ? 'yes' : 'no');
        return $this->redirectToRoute('jury_language', ['langId' => $langId]);
    }

    #[Route(path: '/{langId}/toggle-judge', name: 'jury_language_toggle_judge')]
    public function toggleJudgeAction(
        RouterInterface $router,
        Request $request,
        string $langId
    ): Response {
        $language = $this->em->getRepository(Language::class)->find($langId);
        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s not found', $langId));
        }

        $enabled = $request->request->getBoolean('value');
        $language->setAllowJudge($enabled);
        $this->em->flush();

        if ($enabled) {
            $this->dj->unblockJudgeTasksForLanguage($langId);
        }

        $this->dj->auditlog('language', $langId, 'set allow judge',
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
        $language = $this->em->getRepository(Language::class)->find($langId);
        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s not found', $langId));
        }

        $enabled = $request->request->getBoolean('value');
        $language->setFilterCompilerFiles($enabled);
        $this->em->flush();

        $this->dj->auditlog('language', $langId, 'set filter compiler flags',
            $request->request->getBoolean('value') ? 'yes' : 'no');
        return $this->redirectToRoute('jury_language', ['langId' => $langId]);
    }

    /**
     * @return array{
     *     language: Language,
     *     form: FormInterface
     * }|RedirectResponse
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/{langId}/edit', name: 'jury_language_edit')]
    #[Template(template: 'jury/language_edit.html.twig')]
    public function editAction(Request $request, string $langId): array|RedirectResponse
    {
        $language = $this->em->getRepository(Language::class)->find($langId);
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
                $this->dj->unblockJudgeTasksForLanguage($langId);
            }
            return $this->redirectToRoute('jury_language', ['langId' => $language->getLangid()]);
        }

        return [
            'language' => $language,
            'form' => $form,
        ];
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/{langId}/delete', name: 'jury_language_delete')]
    public function deleteAction(Request $request, string $langId): Response
    {
        $language = $this->em->getRepository(Language::class)->find($langId);
        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s not found', $langId));
        }

        return $this->deleteEntities($request, [$language], $this->generateUrl('jury_languages')
        );
    }

    #[Route(path: '/{langId}/request-remaining', name: 'jury_language_request_remaining')]
    public function requestRemainingRunsWholeLanguageAction(string $langId): RedirectResponse
    {
        $language = $this->em->getRepository(Language::class)->find($langId);
        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s not found', $langId));
        }
        $contestId = $this->dj->getCurrentContest()->getCid();
        $this->judgeRemaining(contestId: $contestId, langId: $langId);
        return $this->redirectToRoute('jury_language', ['langId' => $langId]);
    }
}
