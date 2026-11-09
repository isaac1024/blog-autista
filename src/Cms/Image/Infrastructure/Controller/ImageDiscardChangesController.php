<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageChangesDiscarder;
use Cms\Image\Application\ImageNotFound;
use Cms\Image\Domain\ImageRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Infrastructure\Http\CsrfRequestGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class ImageDiscardChangesController extends AbstractController
{
    #[Route('/images/{id}/discard', name: 'cms_images_discard', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, Request $request, ImageRepository $imageRepository, EventBus $eventBus, CsrfRequestGuard $csrf): Response
    {
        $csrf->assertValid($request);

        try {
            new ImageChangesDiscarder($id)($imageRepository, $eventBus);
        } catch (ImageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told in the editor, where it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_images_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.image.discarded', 'parameters' => []]);

        return $this->redirectToRoute('cms_images_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
