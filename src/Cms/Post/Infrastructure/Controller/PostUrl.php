<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Post\Application\PostResponse;
use Cms\Shared\Infrastructure\Ui\CmsSectionSlugs;
use Shared\Infrastructure\Ui\PostAddressUrl;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Where the site serves the posts the CMS has, while they are there: by the
 * day each was first published and its slug, under the slug of its section.
 */
final readonly class PostUrl
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private CmsSectionSlugs $sectionSlugs,
    ) {}

    /**
     * Where the site serves the post now, null while it isn't there.
     */
    public function onSite(PostResponse $post, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        return $this->allOnSite([$post], $referenceType)[$post->id];
    }

    /**
     * Where the site serves each post now, by id, their sections read at
     * once; null for one off the site.
     *
     * @param list<PostResponse> $posts
     *
     * @return array<string, ?string>
     */
    public function allOnSite(array $posts, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): array
    {
        $slugs = ($this->sectionSlugs)(array_values(array_filter(array_map(static fn (PostResponse $post): ?string => $post->publishedSectionId, $posts), static fn (?string $id): bool => null !== $id)));
        $urls = [];

        foreach ($posts as $post) {
            $section = null === $post->publishedSectionId ? null : $slugs[$post->publishedSectionId] ?? null;
            $urls[$post->id] = null === $section || null === $post->publishedDay || null === $post->publishedSlug
                ? null
                : PostAddressUrl::generate($this->urlGenerator, $section, $post->publishedDay, $post->publishedSlug, $referenceType);
        }

        return $urls;
    }
}
