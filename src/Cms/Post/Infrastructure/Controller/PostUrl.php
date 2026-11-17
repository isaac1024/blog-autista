<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Post\Application\PostResponse;
use Shared\Infrastructure\Ui\PostAddressUrl;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Where the site serves a post the CMS has, while it is there.
 */
final readonly class PostUrl
{
    public function __construct(private UrlGeneratorInterface $urlGenerator) {}

    /**
     * Where the site serves the post now, null while it isn't there.
     */
    public function onSite(PostResponse $post, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        if (null === $post->publishedSectionSlug || null === $post->publishedDay || null === $post->publishedSlug) {
            return null;
        }

        return PostAddressUrl::generate($this->urlGenerator, $post->publishedSectionSlug, $post->publishedDay, $post->publishedSlug, $referenceType);
    }
}
