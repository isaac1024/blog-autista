<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageNotFound;
use Cms\Image\Application\ImageUpdater;
use Cms\Image\Domain\ImageRepository;
use Cms\Shared\Infrastructure\Ui\KeptEdit;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;
use Shared\Infrastructure\RichText\TiptapRichText;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class ImageUpdateController extends AbstractController
{
    public function __construct(private readonly TiptapRichText $tiptapRichText, private readonly KeptEdit $keptEdit) {}

    #[Route('/images/{id}', name: 'cms_images_update', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('cms_images_update')]
    public function __invoke(string $id, Request $request, ImageRepository $imageRepository, EventBus $eventBus, Transaction $transaction): Response
    {
        try {
            new ImageUpdater(
                $id,
                $request->request->getString('alt'),
                // The editor posts its own document, which only this adapter knows.
                $this->tiptapRichText->toStored($request->request->getString('caption')),
            )($imageRepository, $eventBus, $transaction);
        } catch (ImageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // What was written goes back to the editor with why it was refused.
            $this->keptEdit->keep($request, $this->generateUrl('cms_images_edit', ['id' => $id]));
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_images_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.image.saved', 'parameters' => []]);

        // The editor stays on the image, to go on with it.
        return $this->redirectToRoute('cms_images_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
