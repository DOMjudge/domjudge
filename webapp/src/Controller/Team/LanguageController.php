<?php declare(strict_types=1);

namespace App\Controller\Team;
use App\Controller\BaseController;
use App\Entity\Language;
use App\Service\ConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
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
        protected readonly ConfigurationService   $config,
        protected readonly EntityManagerInterface $em
    ) {}

    #[Route(path: '/', name: 'team_languages')]
    public function languagesAction(): Response
    {
        $languagesEnabled = $this->config->get('show_language_versions');
        if (!$languagesEnabled) {
            throw new BadRequestHttpException("You are not allowed to view this page.");
        }
        /** @var Language[] $languages */
        $languages = $this->em->createQueryBuilder()
            ->select('l')
            ->from(Language::class, 'l')
            ->andWhere('l.allowSubmit = 1')
            ->orderBy('l.langid')
            ->getQuery()->getResult();
        return $this->render('team/languages.html.twig', ['languages' => $languages]);
    }
}
