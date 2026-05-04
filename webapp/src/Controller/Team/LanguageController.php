<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Entity\ContestProblem;
use App\Entity\Language;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Ds\Set;
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
     * @param array<string, array{'problems': ContestProblem[], 'language': Language}> $languages
     * @return array<string, array{'problems': ContestProblem[], 'language': Language}>
     */
    private function addLanguage(array $languages, Language $language, ?ContestProblem $problem = null): array
    {
        $langId = $language->getLangid();
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
        $currentContest = $this->dj->getCurrentContest();
        $languages = [];
        $limited = false;
        $allLanguages = new Set();
        // Get all languages specific to the contest, if those are empty all globally enabled languages
        foreach ($this->dj->getAllowedLanguagesForContest($currentContest) as $language) {
            $allLanguages->add($language);
        }
        if ($currentContest) {
            // Add the problem specific languages
            $currentContestProblems = $currentContest->getProblems();
            foreach ($currentContestProblems as $problem) {
                $problemLanguages = new Set($problem->getProblem()->getLanguages());
                if ($problemLanguages->isEmpty()) {
                    continue;
                }
                if (!Utils::equalSets($allLanguages, $problemLanguages)) {
                    if (!$allLanguages->isEmpty()) {
                        $limited = true;
                    }
                    $allLanguages->merge($problem->getProblem()->getLanguages());
                }
            }
            foreach ($currentContestProblems as $problem) {
                $problemLanguages = count($problem->getProblem()->getLanguages()) ? new Set($problem->getProblem()->getLanguages()) : $allLanguages;
                foreach ($problemLanguages as $language) {
                    $languages = $this->addLanguage($languages, $language, $problem);
                }
            }
        } else {
            foreach ($allLanguages as $language) {
                $languages = $this->addLanguage($languages, $language);
            }
        }
        return $this->render('team/languages.html.twig', ['languages' => $languages, 'limited' => $limited]);
    }
}
