<?php

namespace DOMJudgeBundle\Controller\API;

use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Rest\Route("/api/contests/{cid}/languages", defaults={ "_format" = "json" })
 * @Rest\Prefix("/languages")
 * @Rest\NamePrefix("_language_")
 */
class LanguageController extends AbstractRestController
{
    /**
     * @inheritdoc
     */
    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Language', 'lang')
            ->select('lang')
            ->where('lang.allow_submit = 1');
    }

    /**
     * Return the field used as ID in requests
     * @return string
     */
    protected function getIdField(): string
    {
        return 'lang.externalid';
    }
}
