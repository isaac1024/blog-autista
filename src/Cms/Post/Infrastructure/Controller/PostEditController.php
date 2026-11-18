<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Author\Application\AllAuthorsFinder;
use Cms\Author\Domain\AuthorRepository;
use Cms\Post\Application\PostFinder;
use Cms\Post\Application\PostNotFound;
use Cms\Post\Domain\PostRepository;
use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Application\TermsOfTaxonomyByIdFinder;
use Cms\Taxonomy\Application\TermsOfTaxonomyFinder;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\Clock;
use Shared\Infrastructure\Http\SignedUrls;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The editor of a post, with the sections and authors to file and sign it
 * with, and a link to its preview that anyone can open for a while.
 */
final class PostEditController extends AbstractController
{
    /**
     * How long a shared preview link lets anyone see the post.
     */
    private const string SHARED_PREVIEW_VALIDITY = 'P7D';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PostEditor $postEditor,
    ) {}

    #[Route('/posts/{id}', name: 'cms_posts_edit', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, PostRepository $postRepository, TermRepository $termRepository, AuthorRepository $authorRepository, SignedUrls $signedUrls): Response
    {
        try {
            $post = new PostFinder($id)($postRepository);
        } catch (PostNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        // Signed anew each time the editor loads, valid for a while from now.
        $expiresAt = Clock::now()->add(new \DateInterval(self::SHARED_PREVIEW_VALIDITY));
        $previewUrl = $signedUrls->sign(
            $this->generateUrl('cms_posts_preview', ['id' => $post->id], UrlGeneratorInterface::ABSOLUTE_URL),
            $expiresAt,
        );

        return $this->pageRenderer->render(($this->postEditor)(
            $post,
            new TermsOfTaxonomyFinder(TermResponse::SECTION)($termRepository),
            new AllAuthorsFinder()($authorRepository),
            new TermsOfTaxonomyByIdFinder(TermResponse::TAG, $post->tagIds)($termRepository),
            $previewUrl,
            $expiresAt,
        ));
    }
}
