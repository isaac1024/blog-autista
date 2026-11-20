<?php

declare(strict_types=1);

namespace Web\Post\Infrastructure\Controller;

use Shared\Domain\ImageCopy;
use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Infrastructure\Ui\StructuredData;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Author\Application\AuthorResponse;
use Web\Author\Application\AuthorsOnSiteFinder;
use Web\Author\Domain\AuthorRepository;
use Web\Post\Application\IndexedPostsFinder;
use Web\Post\Application\PostCardResponse;
use Web\Post\Domain\PostRepository;
use Web\Shared\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Shared\Infrastructure\Ui\SitePostCards;

/**
 * The entries of the feed: the latest posts search engines may index, each
 * summed up as everywhere else on the site, and signed by its author as
 * the site has them now, or as the author their address now leads to, by
 * the name the post kept otherwise.
 *
 * @phpstan-type FeedEntry array{url: string, title: string, published: \DateTimeImmutable, updated: \DateTimeImmutable, author: string, summary: ?string, image: ?string}
 */
final readonly class FeedEntries
{
    public function __construct(
        private PostRepository $postRepository,
        private AuthorRepository $authorRepository,
        private SitePostCards $sitePostCards,
        private SiteRedirectsByOrigin $redirectsByOrigin,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @return list<FeedEntry>
     */
    public function __invoke(int $limit): array
    {
        $posts = new IndexedPostsFinder($limit)($this->postRepository);
        $summaries = $this->sitePostCards->summaries($posts, UrlGeneratorInterface::ABSOLUTE_URL);
        $authors = new ReferenceResolver('author', array_map(static fn (PostCardResponse $post): string => $post->authorId, $posts), null)(
            fn (array $ids): array => new AuthorsOnSiteFinder($ids)($this->authorRepository),
            $this->redirectsByOrigin,
        );
        $home = $this->urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL);

        return array_map(static function (PostCardResponse $post, array $summary) use ($authors, $home): array {
            $author = $authors[$post->authorId] ?? null;

            return [
                'url' => $summary['url'],
                'title' => $post->title,
                'published' => $post->firstPublishedAt,
                'updated' => $post->lastPublishedAt,
                'author' => $author instanceof AuthorResponse ? $author->name : $post->authorName,
                'summary' => $post->metaDescription,
                'image' => null === $summary['image'] ? null : StructuredData::absolute($home, new ImageCopy($summary['image']->path, ImageCopy::LARGEST)->url()),
            ];
        }, $posts, $summaries);
    }
}
