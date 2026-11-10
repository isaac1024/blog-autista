<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageSearcher;
use Cms\Image\Domain\ImageRepository;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The image library, searched by alternative text, a page of it at a time.
 */
final class ImageListController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly ImageList $imageList,
    ) {}

    #[Route('/images', name: 'cms_images', methods: ['GET'])]
    public function __invoke(
        ImageRepository $imageRepository,
        #[MapQueryParameter]
        string $alt = '',
        #[MapQueryParameter]
        int $page = ListPagination::FIRST_PAGE,
    ): Response {
        try {
            $images = new ImageSearcher($alt, $page)($imageRepository);
        } catch (InvalidCriteria $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render(($this->imageList)($images, $alt, $page));
    }
}
