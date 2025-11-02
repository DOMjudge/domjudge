<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Entity\Language;
use App\Entity\ContestProblem;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
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
     * @param Language[] $languages
     * @return Language[]
     */
    private function addLanguage(array $languages, Language $language, ContestProblem $problem): array
    {
        $langId = $language->getName();
        if (!isset($languages[$langId])) {
            $languages[$langId] = ['problems' => [], 'language' => $language];
        }
        $languages[$langId]['problems'][] = $problem;
        return $languages;
    }

    #[Route(path: '', name: 'team_languages')]
    public function languagesAction(): Response
    {
        $languagesEnabled = $this->config->get('show_language_versions');
        if (!$languagesEnabled) {
            throw new BadRequestHttpException("You are not allowed to view this page.");
        }
        $languages = [];
        $currentContest = $this->dj->getCurrentContest();
        $limited = false;
        foreach ($this->dj->getCurrentContest()->getProblems() as $problem) {
            foreach ($problem->getProblem()->getLanguages() as $language) {
                $languages = $this->addLanguage($languages, $language, $problem);
                $limited = true;
            }
            if (count($problem->getProblem()->getLanguages()) == 0) {
                foreach ($this->dj->getAllowedLanguagesForContest($currentContest) as $language) {
                    $languages = $this->addLanguage($languages, $language, $problem);
                }
            }
        }
        return $this->render('team/languages.html.twig', ['languages' => $languages, 'limited' => $limited]);
    }
}
