<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageFinder;
use Cms\Image\Application\ImageNotFound;
use Cms\Image\Domain\ImageRepository;
use Shared\Domain\DomainError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The images a rich text embeds, by their ids, for the editor to show them
 * as they are now. An id of no image is left out.
 */
final class ImageLookupController extends AbstractController
{
    public function __construct(private readonly EditorImage $editorImage) {}

    #[Route('/images/lookup', name: 'cms_images_lookup', methods: ['GET'])]
    public function __invoke(
        ImageRepository $imageRepository,
        #[MapQueryParameter]
        string $ids = '',
    ): JsonResponse {
        $images = [];

        foreach (array_filter(explode(',', $ids), static fn (string $id): bool => '' !== $id) as $id) {
            try {
                $images[] = ($this->editorImage)(new ImageFinder($id)($imageRepository));
            } catch (ImageNotFound|DomainError) {
                // Not an image, or not one any longer: nothing to show.
            }
        }

        return new JsonResponse(['images' => $images]);
    }
}
