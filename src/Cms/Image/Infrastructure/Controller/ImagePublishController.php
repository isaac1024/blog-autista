<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageNotFound;
use Cms\Image\Application\ImagePublisher;
use Cms\Image\Domain\ImageRepository;
use Cms\Redirect\Application\PreviousAddressDecision;
use Cms\Redirect\Application\ResourcePublication;
use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Domain\MutualExclusion;
use Shared\Domain\Transaction;
use Shared\Infrastructure\Http\CsrfRequestGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class ImagePublishController extends AbstractController
{
    public function __construct(
        private readonly CsrfRequestGuard $csrf,
        private readonly RedirectRepository $redirectRepository,
        private readonly SiteResources $siteResources,
        private readonly MutualExclusion $mutualExclusion,
        private readonly Transaction $transaction,
    ) {}

    /**
     * The address of an image never changes, so publishing it asks nothing:
     * a redirect at it goes, as the image answers it again.
     */
    #[Route('/images/{id}/publish', name: 'cms_images_publish', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, Request $request, ImageRepository $imageRepository, EventBus $eventBus): Response
    {
        $this->csrf->assertValid($request);

        try {
            new ResourcePublication(
                RedirectId::random()->value(),
                Resource::image($id),
                PreviousAddressDecision::keep(),
                null,
                static function () use ($id, $imageRepository, $eventBus): void {
                    new ImagePublisher($id)($imageRepository, $eventBus);
                },
            )($this->redirectRepository, $this->siteResources, $this->mutualExclusion, $this->transaction);
        } catch (ImageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_images_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.image.published', 'parameters' => []]);

        // The editor stays on the image, to go on with it.
        return $this->redirectToRoute('cms_images_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
