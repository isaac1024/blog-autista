<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageNotFound;
use Cms\Image\Application\ImageUnpublisher;
use Cms\Image\Domain\ImageRedirects;
use Cms\Image\Domain\ImageRepository;
use Cms\Redirect\Application\ResourceRetirement;
use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Shared\Infrastructure\Publication\RetirementRequest;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Domain\MutualExclusion;
use Shared\Domain\Transaction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Takes an image off the site with what the editor chose for its addresses.
 * When a choice is refused, the form comes back with them all as they were.
 *
 * It reaches the redirects as much as the image, so its coupling says
 * nothing about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class ImageUnpublishController extends AbstractController
{
    public function __construct(
        private readonly RedirectRepository $redirectRepository,
        private readonly SiteResources $siteResources,
        private readonly MutualExclusion $mutualExclusion,
        private readonly Transaction $transaction,
    ) {}

    #[Route('/images/{id}/unpublish', name: 'cms_images_unpublish', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('cms_images_unpublish')]
    public function __invoke(string $id, Request $request, ImageRepository $imageRepository, EventBus $eventBus, ImageRedirects $imageRedirects): Response
    {
        try {
            new ResourceRetirement(
                RedirectId::random()->value(),
                Resource::image($id),
                RetirementRequest::address($request),
                RetirementRequest::incoming($request),
                static function () use ($id, $imageRepository, $eventBus, $imageRedirects): void {
                    new ImageUnpublisher($id)($imageRepository, $eventBus, $imageRedirects);
                },
            )($this->redirectRepository, $this->siteResources, $this->mutualExclusion, $this->transaction);
        } catch (ImageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the form it goes back to, with every choice as it was.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);
            $request->getSession()->set(\sprintf(ImageUnpublishFormController::SAVED, $id), $request->request->all());

            return $this->redirectToRoute('cms_images_unpublish_form', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.image.unpublished', 'parameters' => []]);

        // The editor stays on the image, to go on with it.
        return $this->redirectToRoute('cms_images_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
