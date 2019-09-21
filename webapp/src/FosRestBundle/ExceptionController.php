<?php declare(strict_types=1);

namespace App\FosRestBundle;

use FOS\RestBundle\Controller\ExceptionController as FosRestExceptionController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ExceptionController
 * @package App\Controller\API
 */
class ExceptionController extends FosRestExceptionController
{
    /**
     * @inheritDoc
     */
    protected function createView(
        \Exception $exception,
        $code,
        array $templateData,
        Request $request,
        $showException
    ) {
        // We overwrite this to
        $view = parent::createView($exception, $code, $templateData, $request,
                                   $showException);
        $view->setFormat('json');
        return $view;
    }
}
