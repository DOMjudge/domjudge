<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/public/blog')]
class BlogController extends BaseController
{
    #[Route(path: '/{id}', name: 'public_blogpost')]
    public function blogPostAction(int $id): Response {
        return $this->render('public/blog_post.html.twig');
    }
}
