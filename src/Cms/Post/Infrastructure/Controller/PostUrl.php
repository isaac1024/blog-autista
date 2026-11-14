<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Post\Application\PostResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Where the site serves a post: under the slug of its section, on the day it
 * was first published, by its slug.
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

        return $this->atAddress($post->publishedSectionSlug, $post->publishedDay, $post->publishedSlug, $referenceType);
    }

    /**
     * @param string $day as Y-m-d
     */
    public function atAddress(string $section, string $day, string $slug, int $referenceType): string
    {
        [$year, $month, $date] = explode('-', $day);

        return $this->urlGenerator->generate('web_post', [
            'section' => $section,
            'year' => $year,
            'month' => $month,
            'day' => $date,
            'slug' => $slug,
        ], $referenceType);
    }
}
