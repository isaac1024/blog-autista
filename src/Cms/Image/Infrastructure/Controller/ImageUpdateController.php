<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageNotFound;
use Cms\Image\Application\ImageUpdater;
use Cms\Image\Domain\ImageRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Infrastructure\RichText\TiptapRichText;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class ImageUpdateController extends AbstractController
{
    public function __construct(private readonly TiptapRichText $tiptapRichText) {}

    #[Route('/images/{id}', name: 'cms_images_update', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, Request $request, ImageRepository $imageRepository, EventBus $eventBus): Response
    {
        try {
            new ImageUpdater(
                $id,
                $request->request->getString('alt'),
                // The editor posts its own document, which only this adapter knows.
                $this->tiptapRichText->toStored($request->request->getString('caption')),
            )($imageRepository, $eventBus);
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
