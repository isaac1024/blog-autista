<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Application\ResourceImageFinder;
use Cms\External\Domain\ExternalResourceRepository;
use Shared\Infrastructure\Http\SignedUrls;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Serves the image of an external resource, the one being edited or the
 * one on the site, to whoever holds a signed link to it, as its editor
 * gives. Each file has a name of its own and never changes, so browsers
 * keep it for good.
 */
final class ExternalImageController extends AbstractController
{
    public function __construct(
        private readonly SignedUrls $signedUrls,
        #[Autowire(param: 'cms.external.image_directory')]
        private readonly string $directory,
    ) {}

    #[Route('/preview/externals/{id}/image/{file}', name: 'cms_externals_image', requirements: ['id' => Requirement::UUID, 'file' => '[0-9a-f-]{36}\.webp'], methods: ['GET'])]
    public function __invoke(string $id, string $file, Request $request, ExternalResourceRepository $resourceRepository): BinaryFileResponse
    {
        // The signature is the only permission: a link changed or expired grants none.
        if (!$this->signedUrls->isValid($request)) {
            throw new AccessDeniedHttpException('The image link is not valid, or has expired.');
        }

        try {
            $path = \sprintf('%s/%s', $this->directory, new ResourceImageFinder($id, $file)($resourceRepository));
        } catch (ExternalResourceNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        if (!is_file($path)) {
            throw $this->createNotFoundException('The image file does not exist.');
        }

        $response = new BinaryFileResponse($path, headers: [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($path));
        $response->setPrivate();
        $response->setMaxAge(31536000);
        $response->setImmutable();

        return $response;
    }
}
