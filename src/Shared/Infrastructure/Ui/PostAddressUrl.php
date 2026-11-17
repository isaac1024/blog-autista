<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Where the site serves a post: under the slug of its section, on the day it
 * was first published, by its slug. The site and the CMS both ask it, so
 * every link, card, redirect and entry of the sitemap of a post is its
 * canonical address.
 */
final class PostAddressUrl
{
    /**
     * @param string $day the day it was first published, as Y-m-d
     */
    public static function generate(UrlGeneratorInterface $urlGenerator, string $section, string $day, string $slug, int $referenceType): string
    {
        [$year, $month, $date] = explode('-', $day);

        return $urlGenerator->generate('web_post', [
            'section' => $section,
            'year' => $year,
            'month' => $month,
            'day' => $date,
            'slug' => $slug,
        ], $referenceType);
    }
}
