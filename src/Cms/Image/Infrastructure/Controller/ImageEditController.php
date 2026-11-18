<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageFinder;
use Cms\Image\Application\ImageNotFound;
use Cms\Image\Domain\ImageRepository;
use Cms\Shared\Infrastructure\Ui\KeptEdit;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Where an image is edited, as ImageEditor composes it.
 */
final class ImageEditController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly ImageEditor $imageEditor,
        private readonly KeptEdit $keptEdit,
    ) {}

    #[Route('/images/{id}', name: 'cms_images_edit', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, ImageRepository $imageRepository, Request $request): Response
    {
        try {
            $image = new ImageFinder($id)($imageRepository);
        } catch (ImageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render(KeptEdit::onto(($this->imageEditor)($image), $this->keptEdit->taken($request)));
    }
}
