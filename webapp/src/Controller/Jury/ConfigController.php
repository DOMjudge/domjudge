<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Entity\Configuration;
use App\Entity\Judging;
use App\Service\CheckConfigService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    protected $CheckConfigService;

    /**
     * TeamCategoryController constructor.
     * @param EntityManagerInterface $em
     * @param LoggerInterface        $logger
     * @param DOMJudgeService        $dj
     * @param CheckConfigService     $checkConfigService
     */
    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        DOMJudgeService $dj,
        CheckConfigService $checkConfigService
    ) {
        $this->em                 = $em;
        $this->logger             = $logger;
        $this->dj                 = $dj;
        $this->checkConfigService = $checkConfigService;
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
     * @param EventLogService   $eventLogService
     * @param Request           $request
     */
    public function indexAction(EventLogService $eventLogService, Request $request)
    {
        /** @var Configuration[] */
        $options = $this->em->getRepository(Configuration::class)->findAll();
        if ($request->getMethod() == 'POST' && $request->request->has('save')) {
            $this->addFlash('scoreboard_refresh', 'After changing specific ' .
                            'settings, you might need to refresh the scoreboard.');

            $needs_merge = false;
            foreach ($options as $option) {
                if (!$request->request->has('config_' . $option->getName())) {
                    // Special-case bool, since checkboxes don't return a
                    // value when unset.
                    if ( $option->getType() != 'bool' ) continue;
                    $val = false;
                } else {
                    $val = $request->request->get('config_' . $option->getName());
                }
                if ($option->getName() == 'verification_required' &&
                    $option->getValue() && !$val ) {
                    // If toggled off, we have to send events for all judgings
                    // that are complete, but not verified yet. Scoreboard
                    // cache refresh should take care of the rest. See #645.
                    $this->logUnverifiedJudgings($eventLogService);
                    $needs_merge = true;
                }
                $old_val = $option->getValue();
                switch ( $option->getType() ) {
                    case 'bool':
                        $option->setValue((bool)$val);
                        break;

                    case 'int':
                        $option->setValue((int)$val);
                        break;

                    case 'string':
                        $option->setValue($val);
                        break;

                    case 'array_val':
                        $result = array();
                        foreach ($val as $data) {
                            if (!empty($data)) {
                                $result[] = $data;
                            }
                        }
                        $option->setValue($result);
                        break;

                    case 'array_keyval':
                        $result = array();
                        foreach ($val as $data) {
                            if (!empty($data['key'])) {
                                $result[$data['key']] = $data['val'];
                            }
                        }
                        $option->setValue($result);
                        break;

                    default:
                        $this->logger->warn(
                            "configation option '%s' has unknown type '%s'",
                            [ $option->getName(), $option->getType() ]
                        );
                }
                if ($option->getValue() != $old_val) {
                    $val_json = $this->dj->jsonEncode($option->getValue());
                    $this->dj->auditlog('configuration', $option->getName(), 'updated', $val_json);
                }
            }

            if ( $needs_merge ) {
                foreach ($options as $option) $this->em->merge($option);
            }

            $this->em->flush();
            return $this->redirectToRoute('jury_config');
        }

        /** @var Configuration[] */
        $options = $this->em->getRepository(Configuration::class)->findAll();
        $categories = array();
        foreach ($options as $option) {
            if (!in_array($option->getCategory(), $categories)) {
                $categories[] = $option->getCategory();
            }
        }
        $all_data = array();
        foreach ($categories as $category) {
            $data = array();
            foreach ($options as $option) {
                if ($option->getCategory() !== $category) {
                    continue;
                }
                $data[] = array(
                    'name' => $option->getName(),
                    'type' => $option->getType(),
                    'value' => $option->getValue(),
                    'description' => $option->getDescription()
                );
            }
            $all_data[] = array(
                'name' => $category,
                'data' => $data
            );
        }
        return $this->render('jury/config.html.twig', [
            'options' => $all_data,
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
