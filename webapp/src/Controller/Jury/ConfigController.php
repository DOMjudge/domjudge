<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Entity\Configuration;
use App\Entity\Judging;
use App\Service\CheckConfigService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/config")
 * @IsGranted("ROLE_ADMIN")
 */
class ConfigController extends AbstractController
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var CheckConfigService
     */
    protected $checkConfigService;

    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * TeamCategoryController constructor.
     * @param EntityManagerInterface $em
     * @param LoggerInterface        $logger
     * @param DOMJudgeService        $dj
     * @param CheckConfigService     $checkConfigService
     * @param ConfigurationService $config
     */
    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        DOMJudgeService $dj,
        CheckConfigService $checkConfigService,
        ConfigurationService $config
    ) {
        $this->em                 = $em;
        $this->logger             = $logger;
        $this->dj                 = $dj;
        $this->checkConfigService = $checkConfigService;
        $this->config             = $config;
    }

    private function logUnverifiedJudgings(EventLogService $eventLogService)
    {
        /** @var Judging[] $judgings */
        $judgings = $this->em->getRepository(Judging::class)->findBy(
            [ 'verified' => 0, 'valid' => 1]
        );

        $judgings_per_contest = [];
        foreach ($judgings as $judging) {
            $judgings_per_contest[$judging->getCid()][] = $judging->getJudgingid();
        }

        // Log to event table; normal cases are handled in:
        // * API/JudgehostController::addJudgingRunAction
        // * Jury/SubmissionController::verifyAction
        foreach ($judgings_per_contest as $cid => $judging_ids) {
            $eventLogService->log('judging', $judging_ids, 'update', $cid);
        }

        $this->logger->info("created events for unverified judgings");
    }

    /**
     * @Route("", name="jury_config")
     * @param EventLogService $eventLogService
     * @param Request $request
     * @return RedirectResponse|Response
     * @throws Exception
     */
    public function indexAction(EventLogService $eventLogService, Request $request)
    {
        $specs = $this->config->getConfigSpecification();
        foreach ($specs as &$spec) {
            $spec = $this->config->addOptions($spec);
        }
        unset($spec);
        /** @var Configuration[] $options */
        $options = $this->em->createQueryBuilder()
            ->from(Configuration::class, 'c',  'c.name')
            ->select('c')
            ->getQuery()
            ->getResult();
        if ($request->getMethod() == 'POST' && $request->request->has('save')) {
            $this->addFlash('scoreboard_refresh', 'After changing specific ' .
                            'settings, you might need to refresh the scoreboard.');

            $needsMerge = false;
            foreach ($specs as $specName => $spec) {
                $oldValue = $spec['default_value'];
                if (isset($options[$specName])) {
                    $optionToSet = $options[$specName];
                    $oldValue = $optionToSet->getValue();
                    $optionIsNew = false;
                } else {
                    $optionToSet = new Configuration();
                    $optionToSet->setName($specName);
                    $optionIsNew = true;
                }
                if (!$request->request->has('config_' . $specName)) {
                    if ($spec['type'] == 'bool') {
                        // Special-case bool, since checkboxes don't return a
                        // value when unset.
                        $val = false;
                    } elseif ($spec['type'] == 'array_val' && isset($spec['options'])) {
                        // Special-case array_val with options, since multiselects
                        // don't return a value when unset.
                        $val = [];
                    } else {
                        continue;
                    }
                } else {
                    $val = $request->request->get('config_' . $specName);
                }
                if ($specName == 'verification_required' &&
                    $oldValue && !$val ) {
                    // If toggled off, we have to send events for all judgings
                    // that are complete, but not verified yet. Scoreboard
                    // cache refresh should take care of the rest. See #645.
                    $this->logUnverifiedJudgings($eventLogService);
                    $needsMerge = true;
                }
                switch ( $spec['type'] ) {
                    case 'bool':
                        $optionToSet->setValue((bool)$val);
                        break;

                    case 'int':
                        $optionToSet->setValue((int)$val);
                        break;

                    case 'string':
                        $optionToSet->setValue($val);
                        break;

                    case 'array_val':
                        $result = array();
                        foreach ($val as $data) {
                            if (!empty($data)) {
                                $result[] = $data;
                            }
                        }
                        $optionToSet->setValue($result);
                        break;

                    case 'array_keyval':
                        $result = array();
                        foreach ($val as $data) {
                            if (!empty($data['key'])) {
                                $result[$data['key']] = $data['val'];
                            }
                        }
                        $optionToSet->setValue($result);
                        break;

                    default:
                        $this->logger->warn(
                            "configation option '%s' has unknown type '%s'",
                            [ $specName, $spec['type'] ]
                        );
                }
                if ($optionToSet->getValue() != $oldValue) {
                    $valJson = $this->dj->jsonEncode($optionToSet->getValue());
                    $this->dj->auditlog('configuration', $specName, 'updated', $valJson);
                    if ($optionIsNew) {
                        $this->em->persist($optionToSet);
                    }
                }
            }

            if ( $needsMerge ) {
                foreach ($options as $option) $this->em->merge($option);
            }

            $this->em->flush();
            return $this->redirectToRoute('jury_config');
        }

        $categories = array();
        foreach ($specs as $spec) {
            if (!in_array($spec['category'], $categories)) {
                $categories[] = $spec['category'];
            }
        }
        $allData = [];
        foreach ($categories as $category) {
            $data = [];
            foreach ($specs as $specName => $spec) {
                if ($spec['category'] !== $category) {
                    continue;
                }
                $data[] = [
                    'name' => $specName,
                    'type' => $spec['type'],
                    'value' => isset($options[$specName]) ?
                        $options[$specName]->getValue() :
                        $spec['default_value'],
                    'description' => $spec['description'],
                    'options' => $spec['options'] ?? null,
                    'key_options' => $spec['key_options'] ?? null,
                    'value_options' => $spec['value_options'] ?? null,
                ];
            }
            $allData[] = [
                'name' => $category,
                'data' => $data
            ];
        }
        return $this->render('jury/config.html.twig', [
            'options' => $allData,
        ]);
    }

    /**
     * @Route("/check", name="jury_config_check")
     */
    public function checkAction(Request $request)
    {
        $results = $this->checkConfigService->runAll();
        return $this->render('jury/config_check.html.twig', [
            'results' => $results
        ]);
    }

    /**
     * @Route("/check/phpinfo", name="jury_config_phpinfo")
     */
    public function phpinfoAction(Request $request)
    {
        ob_start();
        phpinfo();
        $phpinfo = ob_get_contents();
        ob_end_clean();

        return new Response($phpinfo);
    }
}
