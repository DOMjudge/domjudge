<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\BlogPost;
use App\Entity\Role;
use App\Entity\User;
use App\Form\Type\BlogPostType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * @Route("/jury/blog")
 */
class BlogController extends BaseController
{
    private const EDITORJS_IMAGE_BASE_URL = '/media/images/';
    private const THUMBNAILS_DIRECTORY = 'blog/thumbnails';
    private const IN_ARTICLE_IMAGES_DIRECTORY = 'blog/thumbnails';

    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected KernelInterface $kernel;
    protected EventLogService $eventLogService;
    private AsciiSlugger $slugger;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService        $dj,
        KernelInterface        $kernel,
        ConfigurationService   $config,
        EventLogService        $eventLogService
    )
    {
        $this->em = $em;
        $this->dj = $dj;
        $this->config = $config;
        $this->kernel = $kernel;
        $this->eventLogService = $eventLogService;
        $this->slugger = new AsciiSlugger();
    }

    /**
     * @Route("", methods={"GET"}, name="jury_blog")
     */
    public function indexAction(): Response
    {
        /** @var BlogPost[] $blogPosts */
        $blogPosts = $this->em->getRepository(BlogPost::class)->findAll();

        $table_fields = [
            'title' => ['title' => 'title', 'sort' => true],
            'author' => ['title' => 'author', 'sort' => true],
            'publishtime' => ['title' => 'publish time', 'sort' => true,
                'default_sort' => true],
        ];

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $blogPostsTable = [];
        foreach ($blogPosts as $b) {
            /** @var BlogPost $b */
            $blogPostData = [];
            $blogPostActions = [];

            // Get whatever fields we can from the user object itself.
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($b, $k)) {
                    $value = $propertyAccessor->getValue($b, $k);

                    if ($k == 'publishtime') {
                        $value = gmdate('Y-m-d H:i:s', (int)$value);
                    }

                    $blogPostData[$k] = ['value' => $value];
                }
            }

            if ($this->isGranted('ROLE_ADMIN')) {
                $blogPostActions[] = [
                    'icon' => 'edit',
                    'title' => 'edit this blog post',
                    'link' => $this->generateUrl('jury_blog_post_edit', [
                        'id' => $b->getBlogpostid(),
                    ])
                ];
                $blogPostActions[] = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this blog post',
                    'link' => $this->generateUrl('jury_blog_post_delete', [
                        'id' => $b->getBlogpostid(),
                    ]),
                    'ajaxModal' => true,
                ];
            }

            // Save this to our list of rows.
            $blogPostsTable[] = [
                'data' => $blogPostData,
                'actions' => $blogPostActions,
                'link' => $this->generateUrl('jury_blog_post_edit', ['id' => $b->getBlogpostid()]),
            ];
        }

        return $this->render('jury/blog.html.twig', [
            'blog_posts' => $blogPostsTable,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 2 : 0,
        ]);
    }

    /**
     * @Route("/send/image-upload", methods={"POST"}, name="jury_blog_image_upload")
     */
    public function uploadPostImageAction(Request $request): JsonResponse
    {
        /** @var UploadedFile|null $imageFile */
        $imageFile = $request->files->get('image');

        if (!$imageFile) {
            return new JsonResponse(
                ['success' => 0, 'error' => 'No image file found.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $fileName = $this->saveImage($imageFile, self::IN_ARTICLE_IMAGES_DIRECTORY);
        } catch (FileException $e) {
            return new JsonResponse(
                ['success' => 0, 'error' => 'Error uploading the image.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return new JsonResponse([
            'success' => 1,
            'file' => [
                'url' => self::EDITORJS_IMAGE_BASE_URL . $fileName,
            ]
        ]);
    }

    /**
     * @Route("/send", methods={"GET", "POST"}, name="jury_blog_post_send")
     * @Route("/{id}/edit", methods={"GET", "POST"}, name="jury_blog_post_edit")
     */
    public function sendBlogPostAction(Request $request, ?int $id = null): Response
    {
        if ($id) {
            $blogPost = $this->em->getRepository(BlogPost::class)->find($id);
            if (!$blogPost) {
                throw $this->createNotFoundException('No blog post found for id ' . $id);
            }
        } else {
            $blogPost = new BlogPost();
        }

        $form = $this->createForm(BlogPostType::class, $blogPost);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var BlogPost $blogPost */
            $blogPost = $form->getData();

            if (!$id) {
                $blogPost->setPublishtime(Utils::now());
            }

            $slug = strtolower($this->slugger->slug($blogPost->getTitle())->toString());
            if ($this->em->getRepository(BlogPost::class)->findOneBy(['slug' => $slug])) {
                $slug .= '-' . uniqid();
            }
            $blogPost->setSlug($slug);

            $thumbnailFileName = $this->saveImage(
                $form['thumbnail_file_name']->getData(),
                self::THUMBNAILS_DIRECTORY
            );
            $blogPost->setThumbnailFileName($thumbnailFileName);

            $this->em->persist($blogPost);
            $this->em->flush();

            $blogpostId = $blogPost->getBlogpostid();
            $this->dj->auditlog('blog_post', $blogpostId, 'added');
            $this->eventLogService->log('blog_post', $blogpostId, 'create');

            return $this->redirectToRoute('public_blog_post', ['slug' => $blogPost->getSlug()]);
        }

        return $this->renderForm('jury/blog_post_send.html.twig', [
            'form' => $form,
            'action' => $id ? 'edit' : 'send'
        ]);
    }

    /**
     * @Route("/{id}/delete", name="jury_blog_post_delete")
     */
    public function deleteBlogPostAction(Request $request, int $id): Response
    {
        /** @var BlogPost $blogPost */
        $blogPost = $this->em->getRepository(BlogPost::class)->find($id);
        if (!$blogPost) {
            throw new NotFoundHttpException(sprintf('Blog post with ID %s not found', $id));
        }

        return $this->deleteEntities($request, $this->em, $this->dj, $this->eventLogService, $this->kernel,
            [$blogPost], $this->generateUrl('jury_blog'));
    }

    private function saveImage(UploadedFile $file, string $directory): string
    {
        $fileName = md5(uniqid()) . '.' . $file->guessExtension();

        $file->move(
            join('/', [$this->getParameter('image_directory'), $directory]),
            $fileName
        );

        return join('/', [$directory, $fileName]);
    }
}
