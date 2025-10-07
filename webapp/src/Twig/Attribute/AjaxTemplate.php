<?php declare(strict_types=1);

namespace App\Twig\Attribute;

use Attribute;
use Symfony\Bridge\Twig\Attribute\Template;

/**
 * This attribute can be used to render a different template when the request is an AJAX request.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class AjaxTemplate extends Template
{
    /**
     * @param array<mixed, mixed>|null $vars
     */
    public function __construct(
        string $normalTemplate,
        public string $ajaxTemplate,
        ?array $vars = null,
        bool $stream = false,
    ) {
        parent::__construct($normalTemplate, $vars, $stream);
    }
}
