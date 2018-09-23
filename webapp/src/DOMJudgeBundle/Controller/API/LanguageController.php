<?php
namespace DOMJudgeBundle\Controller\API;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Language;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Rest\Route("/api/v5/contests/{cid}/languages", defaults={ "_format" = "json" })
 */
class LanguageController extends FOSRestController
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @Rest\Get("")
     */
    public function getLanguagesAction()
    {
        return $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Language', 'lang')
            ->select('lang')
            ->where('lang.allow_submit = 1')
            ->getQuery()
            ->getResult();
    }

    /**
     * @Rest\Get("/{externalid}")
     */
    public function getLanguageAction(Language $language)
    {
        if (!$language->getAllowSubmit()) {
            throw new NotFoundHttpException();
        }
        return $language;
    }
}
