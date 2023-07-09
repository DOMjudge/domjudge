<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\BlogPost;
use App\Entity\Clarification;
use App\Entity\User;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
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

/**
 * @Route("/public/blog")
 */
class BlogController extends BaseController
{
    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected EventLogService $eventLogService;

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
    }

    /**
     * @Route("", name="public_blog_list")
     */
    public function blogListAction(): Response
    {
        return $this->render('public/blog_list.html.twig');
    }

    /**
     * @Route("/{slug}", name="public_blog_post")
     */
    public function viewAction(string $slug): Response
    {
        /** @var BlogPost $blogPost */
        $blogPost = $this->em->getRepository(BlogPost::class)->findOneBy(['slug' => $slug]);

        if (!$blogPost) {
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
