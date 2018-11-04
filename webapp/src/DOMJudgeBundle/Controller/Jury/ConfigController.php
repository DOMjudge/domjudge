<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\TeamAffiliation;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury")
 * @Security("has_role('ROLE_ADMIN')")
 */
class ConfigController extends Controller
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * TeamCategoryController constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService        $DOMJudgeService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService
    ) {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
    }

    /**
     * @Route("/config", name="jury_config")
     */
    public function indexAction(Request $request, Packages $assetPackage, KernelInterface $kernel)
    {
        /** @var Configuration[] */
        $options = $this->entityManager->getRepository('DOMJudgeBundle:Configuration')->findAll();
        if ($request->getMethod() == 'POST' && $request->request->has('save')) {
            foreach ($options as $option) {
                if ($option->getType() == 'bool') {
                    $val = $request->request->has('config_' . $option->getName());
                    $option->setValue($val);
                    continue;
                }
                if (!$request->request->has('config_' . $option->getName())) {
                    continue;
                }
                if ($option->getType() == 'int' || $option->getType() == 'string') {
                    $option->setValue($request->request->get('config_' . $option->getName()));
                } else if ($option->getType() == 'array_val') {
                    $vals = $request->request->get('config_' . $option->getName());
                    $result = array();
                    foreach ($vals as $data) {
                        if (!empty($data)) {
                            $result[] = $data;
                        }
                    }
                    $option->setValue($result);
                } else if ($option->getType() == 'array_keyval') {
                    $vals = $request->request->get('config_' . $option->getName());
                    $result = array();
                    foreach ($vals as $data) {
                        if (!empty($data['key'])) {
                            $result[$data['key']] = $data['val'];
                        }
                    }
                    $option->setValue($result);
                }
            }
        }
        $this->entityManager->flush();
        /** @var Configuration[] */
        $options = $this->entityManager->getRepository('DOMJudgeBundle:Configuration')->findAll();
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
        return $this->render('@DOMJudge/jury/config.html.twig', [
            'options' => $all_data,
        ]);
    }
}
