<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Entity\Configuration;
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

            $data = [];
            foreach ($request->request->all() as $key => $value) {
                if (strpos($key, 'config_') === 0) {
                    $valueToUse = $value;
                    if (is_array($value)) {
                        $firstItem = reset($value);
                        if (is_array($firstItem) && isset($firstItem['key'])) {
                            $valueToUse = [];
                            foreach ($value as $item) {
                                $valueToUse[$item['key']] = $item['val'];
                            }
                        }
                    }
                    $data[substr($key, strlen('config_'))] = $valueToUse;
                }
            }
            $this->config->saveChanges($data, $eventLogService, $this->dj);
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
