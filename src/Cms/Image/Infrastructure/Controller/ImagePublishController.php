<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageNotFound;
use Cms\Image\Application\ImagePublisher;
use Cms\Image\Domain\ImageRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class ImagePublishController extends AbstractController
{
    #[Route('/images/{id}/publish', name: 'cms_images_publish', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, ImageRepository $imageRepository, EventBus $eventBus): Response
    {
        try {
            new ImagePublisher($id)($imageRepository, $eventBus);
        } catch (ImageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_images_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('cms_images', status: Response::HTTP_SEE_OTHER);
    }
}
