<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageFinder;
use Cms\Image\Application\ImageNotFound;
use Cms\Image\Domain\ImageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Serves an original only through the authenticated CMS, addressed by its id
 * so the request never supplies a filesystem path.
 */
final class ImageFileController extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'cms.image.storage_directory')]
        private readonly string $directory,
    ) {}

    #[Route('/images/{id}/file', name: 'cms_images_file', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, ImageRepository $imageRepository): BinaryFileResponse
    {
        try {
            $image = new ImageFinder($id)($imageRepository);
        } catch (ImageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $path = \sprintf('%s/%s', $this->directory, $image->path);

        if (!is_file($path)) {
            throw $this->createNotFoundException('The image file does not exist.');
        }

        $response = new BinaryFileResponse($path, headers: [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($path));
        $response->setPrivate();
        $response->setMaxAge(31536000);
        $response->setImmutable();

        return $response;
    }
}
