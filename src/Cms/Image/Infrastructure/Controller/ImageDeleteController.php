<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageDeleter;
use Cms\Image\Application\ImageNotFound;
use Cms\Image\Domain\ImageRepository;
use Cms\Image\Domain\ImageStorage;
use Cms\Image\Domain\ImageUsage;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class ImageDeleteController extends AbstractController
{
    #[Route('/images/{id}/delete', name: 'cms_images_delete', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('cms_images_delete')]
    public function __invoke(string $id, ImageRepository $imageRepository, ImageStorage $imageStorage, ImageUsage $imageUsage, EventBus $eventBus, Transaction $transaction): Response
    {
        try {
            new ImageDeleter($id)($imageRepository, $imageStorage, $imageUsage, $eventBus, $transaction);
        } catch (ImageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_images_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.image.deleted', 'parameters' => []]);

        return $this->redirectToRoute('cms_images', status: Response::HTTP_SEE_OTHER);
    }
}
