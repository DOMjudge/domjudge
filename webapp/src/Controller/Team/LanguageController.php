<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Entity\Language;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TEAM')]
#[IsGranted(
    new Expression('user.getTeam() !== null'),
    message: 'You do not have a team associated with your account.'
)]
#[Route(path: '/team/languages')]
class LanguageController extends BaseController
{
    public function __construct(
        protected readonly ConfigurationService $config,
        EntityManagerInterface $em,
        protected readonly EventLogService $eventLogService,
        DOMJudgeService $dj,
        KernelInterface $kernel,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    /**
     * @return array{languages: Language[]}
     */
    #[Route(path: '', name: 'team_languages')]
    #[Template(template: 'team/languages.html.twig')]
    public function languagesAction(): array
    {
        $languagesEnabled = $this->config->get('show_language_versions');
        if (!$languagesEnabled) {
            throw new BadRequestHttpException("You are not allowed to view this page.");
        }
        $currentContest = $this->dj->getCurrentContest();
        /** @var Language[] $languages */
        $languages = $this->dj->getAllowedLanguagesForContest($currentContest);
        return ['languages' => $languages];
    }
}
