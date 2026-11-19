<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageFinder;
use Cms\Image\Application\ImageNotFound;
use Cms\Image\Domain\ImageRepository;
use Cms\Image\Infrastructure\Storage\ImageThumbnails;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Serves the small copy of an image that the lists and pickers of the CMS
 * draw, only through the authenticated CMS, as its original.
 */
final class ImageThumbnailController extends AbstractController
{
    public function __construct(private readonly ImageThumbnails $thumbnails) {}

    #[Route('/images/{id}/thumbnail', name: 'cms_images_thumbnail', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, ImageRepository $imageRepository): BinaryFileResponse
    {
        try {
            $image = new ImageFinder($id)($imageRepository);
        } catch (ImageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $path = $this->thumbnails->made($image->path) ?? throw $this->createNotFoundException('The image file does not exist.');

        $response = new BinaryFileResponse($path, headers: [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($path));
        $response->setPrivate();
        // A thumbnail never changes: a new crop is a new image, with an id of its own.
        $response->setMaxAge(31536000);
        $response->setImmutable();

        return $response;
    }
}
