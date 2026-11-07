<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Where the upload form sends the image and its alternative text. A shell for
 * now: it keeps nothing and goes back to the library.
 */
final class ImageCreateController extends AbstractController
{
    #[Route('/images', name: 'cms_images_create', methods: ['POST'])]
    public function __invoke(): Response
    {
        return $this->redirectToRoute('cms_images', status: Response::HTTP_SEE_OTHER);
    }
}
