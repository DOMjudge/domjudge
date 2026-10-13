<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\PasswordChangeService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PasswordChangeExtension extends AbstractExtension
{
    public function __construct(protected readonly PasswordChangeService $passwordChangeService) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('can_change_password', $this->passwordChangeService->canChangePassword(...)),
        ];
    }
}
