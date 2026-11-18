<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageUploader;
use Cms\Image\Domain\ImageId;
use Cms\Image\Domain\ImageRepository;
use Cms\Image\Domain\ImageStorage;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Uploads an image, cropped and encoded by the browser, with its alternative
 * text. When a rule refuses them, the library tells why.
 */
final class ImageCreateController extends AbstractController
{
    #[Route('/images', name: 'cms_images_create', methods: ['POST'])]
    #[IsCsrfTokenValid('cms_images_create')]
    public function __invoke(Request $request, ImageRepository $imageRepository, ImageStorage $imageStorage, EventBus $eventBus): Response
    {
        $image = $request->files->get('image');
        $id = ImageId::random()->value();

        try {
            new ImageUploader(
                $id,
                $request->request->getString('alt'),
                // The domain refuses an image without its file.
                $image instanceof UploadedFile && $image->isValid() ? $image->getPathname() : '',
            )($imageRepository, $imageStorage, $eventBus);
        } catch (DomainError $error) {
            // Told in the library, where the upload was made.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_images', status: Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.image.uploaded', 'parameters' => []]);

        // The editor goes on with the image just uploaded, which needs a
        // caption before it can be published.
        return $this->redirectToRoute('cms_images_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
