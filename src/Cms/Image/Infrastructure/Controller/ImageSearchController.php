<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageSearcher;
use Cms\Image\Domain\ImageRepository;
use Shared\Domain\Criteria\InvalidCriteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The images the rich text editor offers to embed, newest first, searched by
 * their alternative text.
 */
final class ImageSearchController extends AbstractController
{
    public function __construct(private readonly EditorImage $editorImage) {}

    #[Route('/images/search', name: 'cms_images_search', methods: ['GET'])]
    public function __invoke(
        ImageRepository $imageRepository,
        #[MapQueryParameter]
        string $alt = '',
        #[MapQueryParameter]
        int $page = 1,
    ): JsonResponse {
        try {
            $images = new ImageSearcher($alt, $page)($imageRepository);
        } catch (InvalidCriteria $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return new JsonResponse(['images' => array_map($this->editorImage, $images)]);
    }
}
