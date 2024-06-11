<?php declare(strict_types=1);

namespace App\Entity;

/**
 * Entities implementing this interface will have their external ID prefixed with 'dj-', but only in shadow mode.
 *
 * This is used for submissions and clarifications that are added directly in DOMjudge while shadowing.
 *
 * For submissions this happens for example when analysts use DOMjudge and want to submit something.
 *
 * For clarifications this should normally not happen, but we still need to handle it.
 */
interface PrefixedExternalIdInShadowModeInterface
{
}
