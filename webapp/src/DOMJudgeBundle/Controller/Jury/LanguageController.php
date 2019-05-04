<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Entity\Language;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Form\Type\LanguageType;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Service\SubmissionService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/languages")
 * @Security("has_role('ROLE_JURY')")
 */
class LanguageController extends BaseController
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
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * LanguageController constructor.
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param EventLogService        $eventLogService
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        EventLogService $eventLogService
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
        $this->eventLogService = $eventLogService;
    }

    /**
     * @Route("", name="jury_languages")
     */
    public function indexAction(Request $request, Packages $assetPackage)
    {
        $em = $this->em;
        /** @var Language[] $languages */
        $languages    = $em->createQueryBuilder()
            ->select('lang')
            ->from('DOMJudgeBundle:Language', 'lang')
            ->orderBy('lang.name', 'ASC')
            ->getQuery()->getResult();
        $table_fields = [
            'langid' => ['title' => 'ID/ext', 'sort' => true],
            'name' => ['title' => 'name', 'sort' => true, 'default_sort' => true],
            'entrypoint' => ['title' => 'entry point', 'sort' => true],
            'allowsubmit' => ['title' => 'allow submit', 'sort' => true],
            'allowjudge' => ['title' => 'allow judge', 'sort' => true],
            'timefactor' => ['title' => 'timefactor', 'sort' => true],
            'extensions' => ['title' => 'extensions', 'sort' => true],
        ];

        // Insert external ID field when configured to use it
        if ($externalIdField = $this->eventLogService->externalIdFieldForEntity(Language::class)) {
            $table_fields = array_slice($table_fields, 0, 1, true) +
                [$externalIdField => ['title' => 'external ID', 'sort' => true]] +
                array_slice($table_fields, 1, null, true);
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $languages_table  = [];
        foreach ($languages as $lang) {
            $langdata    = [];
            $langactions = [];
            // Get whatever fields we can from the language object itself
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

            // merge in the rest of the data
            $langdata = array_merge($langdata, [
                'entrypoint' => ['value' => $lang->getRequireEntryPoint() ? 'yes' : 'no'],
                'extensions' => ['value' => implode(', ', $lang->getExtensions())],
                'allowsubmit' => ['value' => $lang->getAllowSubmit() ? 'yes' : 'no'],
                'allowjudge' => ['value' => $lang->getAllowJudge() ? 'yes' : 'no'],
            ]);

            $languages_table[] = [
                'data' => $langdata,
                'actions' => $langactions,
                'link' => $this->generateUrl('jury_language', ['langId' => $lang->getLangid()]),
                'cssclass' => $lang->getAllowSubmit() ? '' : 'disabled',
            ];
        }
        return $this->render('@DOMJudge/jury/languages.html.twig', [
            'languages' => $languages_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 2 : 0,
        ]);
    }

    // Note that the add action appears before the view action to make sure /add is not seen as a language
    /**
     * @Route("/add", name="jury_language_add")
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function addAction(Request $request)
    {
        $language = new Language();

        $form = $this->createForm(LanguageType::class, $language);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Normalize extensions
            if ($language->getExtensions()) {
                $language->setExtensions(array_values($language->getExtensions()));
            }
            $this->em->persist($language);
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $language,
                              $language->getLangid(), true);
            return $this->redirect($this->generateUrl('jury_language',
                                                      ['langId' => $language->getLangid()]));
        }

        return $this->render('@DOMJudge/jury/language_add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{langId}", name="jury_language")
     * @param Request           $request
     * @param SubmissionService $submissionService
     * @param string            $langId
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function viewAction(Request $request, SubmissionService $submissionService, string $langId)
    {
        /** @var Language $language */
        $language = $this->em->getRepository(Language::class)->find($langId);
        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s not found', $langId));
        }

        $restrictions = ['langid' => $language->getLangid()];
        /** @var Submission[] $submissions */
        list($submissions, $submissionCounts) = $submissionService->getSubmissionList(
            $this->dj->getCurrentContests(),
            $restrictions
        );

        $data = [
            'language' => $language,
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'showContest' => count($this->dj->getCurrentContests()) > 1,
            'showExternalResult' => $this->dj->dbconfig_get('data_source', DOMJudgeService::DATA_SOURCE_LOCAL) ==
                DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL,
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_language', ['langId' => $language->getLangid()]),
                'ajax' => true,
            ],
        ];

        // For ajax requests, only return the submission list partial
        if ($request->isXmlHttpRequest()) {
            $data['showTestcases'] = false;
            return $this->render('@DOMJudge/jury/partials/submission_list.html.twig', $data);
        }

        return $this->render('@DOMJudge/jury/language.html.twig', $data);
    }

    /**
     * @Route("/{langId}/toggle-submit", name="jury_language_toggle_submit")
     * @param Request $request
     * @param string  $langId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function toggleSubmitAction(Request $request, string $langId)
    {
        /** @var Language $language */
        $language = $this->em->getRepository(Language::class)->find($langId);
        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s not found', $langId));
        }

        $language->setAllowSubmit($request->request->getBoolean('allow_submit'));
        $this->em->flush();

        $this->dj->auditlog('language', $langId, 'set allow submit',
                                         $request->request->getBoolean('allow_submit'));
        return $this->redirectToRoute('jury_language', ['langId' => $langId]);
    }

    /**
     * @Route("/{langId}/toggle-judge", name="jury_language_toggle_judge")
     * @param Request $request
     * @param string  $langId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function toggleJudgeAction(Request $request, string $langId)
    {
        /** @var Language $language */
        $language = $this->em->getRepository(Language::class)->find($langId);
        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s not found', $langId));
        }

        $language->setAllowJudge($request->request->getBoolean('allow_judge'));
        $this->em->flush();

        $this->dj->auditlog('language', $langId, 'set allow judge',
                                         $request->request->getBoolean('allow_judge'));
        return $this->redirectToRoute('jury_language', ['langId' => $langId]);
    }

    /**
     * @Route("/{langId}/edit", name="jury_language_edit")
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @param string  $langId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function editAction(Request $request, string $langId)
    {
        /** @var Language $language */
        $language = $this->em->getRepository(Language::class)->find($langId);
        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s not found', $langId));
        }

        $form = $this->createForm(LanguageType::class, $language);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Normalize extensions
            if ($language->getExtensions()) {
                $language->setExtensions(array_values($language->getExtensions()));
            }
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $language,
                              $language->getLangid(), false);
            return $this->redirect($this->generateUrl('jury_language',
                                                      ['langId' => $language->getLangid()]));
        }

        return $this->render('@DOMJudge/jury/language_edit.html.twig', [
            'language' => $language,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{langId}/delete", name="jury_language_delete")
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @param string  $langId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function deleteAction(Request $request, string $langId)
    {
        /** @var Language $language */
        $language = $this->em->getRepository(Language::class)->find($langId);
        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s not found', $langId));
        }

        return $this->deleteEntity($request, $this->em, $this->dj, $language, $language->getName(), $this->generateUrl('jury_languages'));
    }
}
