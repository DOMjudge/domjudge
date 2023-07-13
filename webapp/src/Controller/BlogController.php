<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\BlogPost;
use App\Entity\Clarification;
use App\Entity\User;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Setono\EditorJS\Parser\BlockParser\HeaderBlockParser;
use Setono\EditorJS\Parser\BlockParser\ImageBlockParser;
use Setono\EditorJS\Parser\BlockParser\ListBlockParser;
use Setono\EditorJS\Parser\BlockParser\ParagraphBlockParser;
use Setono\EditorJS\Parser\Parser;
use Setono\EditorJS\Renderer\BlockRenderer\DelimiterBlockRenderer;
use Setono\EditorJS\Renderer\BlockRenderer\HeaderBlockRenderer;
use Setono\EditorJS\Renderer\BlockRenderer\ImageBlockRenderer;
use Setono\EditorJS\Renderer\BlockRenderer\ListBlockRenderer;
use Setono\EditorJS\Renderer\BlockRenderer\ParagraphBlockRenderer;
use Setono\EditorJS\Renderer\BlockRenderer\RawBlockRenderer;
use Setono\EditorJS\Renderer\Renderer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/public/blog")
 */
class BlogController extends BaseController
{
    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected EventLogService $eventLogService;

    private int $postsPerPage;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService        $dj,
        ConfigurationService   $config,
        EventLogService        $eventLogService
    )
    {
        $this->em = $em;
        $this->dj = $dj;
        $this->config = $config;
        $this->eventLogService = $eventLogService;

        $this->postsPerPage = $this->config->get('blog_posts_per_page');
    }

    /**
     * @Route("", name="public_blog_list")
     */
    public function listAction(Request $request): Response
    {
        $totalPosts = $this->em->getRepository(BlogPost::class)
            ->createQueryBuilder('bp')
            ->select('count(bp.blogpostid)')
            ->where('bp.publishtime <= :now')
            ->getQuery()
            ->setParameter('now', new DateTime())
            ->getSingleScalarResult();

        $totalPages = ceil($totalPosts / $this->postsPerPage);

        $page = (int)min($request->query->getInt('page', 1), $totalPages);
        $page = (int)max($page, 1);

        $blogPosts = $this->em->getRepository(BlogPost::class)
            ->createQueryBuilder('bp')
            ->orderBy('bp.publishtime', 'DESC')
            ->where('bp.publishtime <= :now')
            ->setFirstResult(($page - 1) * $this->postsPerPage)
            ->setMaxResults($this->postsPerPage)
            ->getQuery()
            ->setParameter('now', new DateTime())
            ->getResult();

        return $this->render('public/blog_list.html.twig', [
            'posts' => $blogPosts,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * @Route("/{slug}", name="public_blog_post")
     */
    public function viewAction(string $slug): Response
    {
        /** @var BlogPost $blogPost */
        $blogPost = $this->em->getRepository(BlogPost::class)->findOneBy(['slug' => $slug]);

        if (!$blogPost || $blogPost->getPublishtime() > new DateTime()) {
            throw new NotFoundHttpException(sprintf('Blog post with slug %s not found', $slug));
        }

        $parser = new Parser();
        $parser->addBlockParser(new ParagraphBlockParser());
        $parser->addBlockParser(new HeaderBlockParser());
        $parser->addBlockParser(new ListBlockParser());
        $parser->addBlockParser(new ImageBlockParser());
        $parserResult = $parser->parse($blogPost->getBody());

        $renderer = new Renderer();
        $renderer->addBlockRenderer(new DelimiterBlockRenderer());
        $renderer->addBlockRenderer(new HeaderBlockRenderer());
        $renderer->addBlockRenderer(new ImageBlockRenderer());
        $renderer->addBlockRenderer(new ListBlockRenderer());
        $renderer->addBlockRenderer(new ParagraphBlockRenderer());
        $renderer->addBlockRenderer(new RawBlockRenderer());

        $body = $renderer->render($parserResult);

        $blogPostData = [
            'title' => $blogPost->getTitle(),
            'subtitle' => $blogPost->getSubtitle(),
            'author' => $blogPost->getAuthor(),
            'body' => $body,
            'publishTime' => $blogPost->getPublishtime(),
        ];

        return $this->render('public/blog_post.html.twig',
            $blogPostData
        );
    }
}
