<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\BlogPost;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use EditorJS\EditorJS;
use EditorJS\EditorJSException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * @Route("/jury/blog")
 */
class BlogController extends BaseController
{
    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected EventLogService $eventLogService;
    private AsciiSlugger $slugger;

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
        $this->slugger = new AsciiSlugger();
    }

    /**
     * @Route("/send", methods={"GET"}, name="jury_blog_post_new")
     */
    public function composeClarificationAction(): Response
    {
        return $this->render('jury/blog_post_new.html.twig');
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
            $fileName = $request->getUriForPath($this->saveImage($imageFile));
        } catch (FileException $e) {
            return new JsonResponse(
                ['success' => 0, 'error' => 'Error uploading the image.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return new JsonResponse([
            'success' => 1,
            'file' => [
                // use a dynamic path
                'url' => '/images/blog/' . $fileName,
            ]
        ]);
    }

    /**
     * @Route("/send", methods={"POST"}, name="jury_blog_post_send")
     */
    public function sendAction(Request $request): Response
    {
        $blogPost = new BlogPost();

        $blogPost->setAuthor($request->request->get('author'));
        $blogPost->setTitle($request->request->get('title'));
        $blogPost->setSubtitle($request->request->get('subtitle'));
        $blogPost->setBody($request->request->get('body'));
        $blogPost->setPublishtime(Utils::now());

        $slug = strtolower($this->slugger->slug($blogPost->getTitle())->toString());
        $blogPost->setSlug($slug);

        $thumbnailFileName = $this->saveImage($request->files->get('thumbnail'));
        $blogPost->setThumbnailFileName($thumbnailFileName);

        $this->em->persist($blogPost);
        $this->em->flush();

        $blogpostId = $blogPost->getBlogpostid();
        $this->dj->auditlog('clarification', $blogpostId, 'added');
        $this->eventLogService->log('clarification', $blogpostId, 'create');

        return $this->redirectToRoute('public_blog_post', ['slug' => $blogPost->getSlug()]);
    }

    private function saveImage(UploadedFile $file): string
    {
        $fileName = md5(uniqid()) . '.' . $file->guessExtension();

        $file->move(
        // ok?
            $this->getParameter('image_directory'),
            $fileName
        );

        return $fileName;
    }
}
