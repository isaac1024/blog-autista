<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Application\AvatarFinder;
use Cms\Author\Domain\AuthorRepository;
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
 * Serves the file of an avatar of an author, the one being edited or the one
 * on the site, to whoever holds a signed link to it, as the editor and the
 * preview of the author give. Each file has a name of its own and never
 * changes, so browsers keep it for good.
 */
final class AvatarFileController extends AbstractController
{
    public function __construct(
        private readonly SignedUrls $signedUrls,
        #[Autowire(param: 'cms.author.avatar_directory')]
        private readonly string $directory,
    ) {}

    #[Route('/preview/authors/{id}/avatar/{file}', name: 'cms_authors_avatar', requirements: ['id' => Requirement::UUID, 'file' => '[0-9a-f-]{36}\.webp'], methods: ['GET'])]
    public function __invoke(string $id, string $file, Request $request, AuthorRepository $authorRepository): BinaryFileResponse
    {
        // The signature is the only permission: a link changed or expired grants none.
        if (!$this->signedUrls->isValid($request)) {
            throw new AccessDeniedHttpException('The avatar link is not valid, or has expired.');
        }

        try {
            $path = \sprintf('%s/%s', $this->directory, new AvatarFinder($id, $file)($authorRepository));
        } catch (AuthorNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        if (!is_file($path)) {
            throw $this->createNotFoundException('The avatar file does not exist.');
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
