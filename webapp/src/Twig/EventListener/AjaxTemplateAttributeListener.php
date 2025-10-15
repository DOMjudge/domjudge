<?php declare(strict_types=1);

namespace App\Twig\EventListener;

use App\Twig\Attribute\AjaxTemplate;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ViewEvent;

class AjaxTemplateAttributeListener
{
    // Priority needs to be > -128 to come before TemplateAttributeListener
    #[AsEventListener]
    public function onKernelView(ViewEvent $event): void
    {
        // Based on Symfony's TemplateAttributeListener

        /** @var AjaxTemplate|null $ajaxTemplateAttribute */
        $ajaxTemplateAttribute = $event->controllerArgumentsEvent?->getAttributes(AjaxTemplate::class)[0] ?? null;
        if (!$ajaxTemplateAttribute) {
            return;
        }

        // Create the template attribute that Symfony can use
        $template = new Template(
            template: $event->getRequest()->isXmlHttpRequest() ? $ajaxTemplateAttribute->ajaxTemplate : $ajaxTemplateAttribute->template,
            vars: $ajaxTemplateAttribute->vars,
            stream: $ajaxTemplateAttribute->stream,
        );
        $attributes = $event->controllerArgumentsEvent?->getAttributes();
        $attributes[Template::class] = [$template];
        $event->controllerArgumentsEvent?->setController($event->controllerArgumentsEvent->getController(), $attributes);
    }
}
