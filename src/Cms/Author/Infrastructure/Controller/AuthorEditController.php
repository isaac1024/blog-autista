<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorFinder;
use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Domain\AuthorRepository;
use Shared\Domain\Clock;
use Shared\Infrastructure\Http\SignedUrls;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The editor of an author, with a link to its preview that anyone can open
 * for a while.
 */
final class AuthorEditController extends AbstractController
{
    /**
     * How long a shared preview link lets anyone see the author.
     */
    private const string SHARED_PREVIEW_VALIDITY = 'P7D';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly AuthorEditor $authorEditor,
    ) {}

    #[Route('/authors/{id}', name: 'cms_authors_edit', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, AuthorRepository $authorRepository, SignedUrls $signedUrls): Response
    {
        try {
            $author = new AuthorFinder($id)($authorRepository);
        } catch (AuthorNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        // Signed anew each time the editor loads, valid for a while from now.
        $expiresAt = Clock::now()->add(new \DateInterval(self::SHARED_PREVIEW_VALIDITY));
        $previewUrl = $signedUrls->sign(
            $this->generateUrl('cms_authors_preview', ['id' => $author->id], UrlGeneratorInterface::ABSOLUTE_URL),
            $expiresAt,
        );

        return $this->pageRenderer->render(($this->authorEditor)($author, $previewUrl, $expiresAt));
    }
}
