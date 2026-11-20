<?php

declare(strict_types=1);

namespace Shared\Domain;

/**
 * The address of a post without its section: /2026/11/07/mi-primer-dia.html
 * for /vida-diaria/2026/11/07/mi-primer-dia.html. The day and the slug tell
 * a post apart; the section only says where it is filed. A redirect of a
 * post answers it, so it applies whatever section an address names.
 */
final class PostPath
{
    private const string PATTERN = '#^/[a-z0-9]+(?:-[a-z0-9]+)*(/\d{4}/\d{2}/\d{2}/[a-z0-9]+(?:-[a-z0-9]+)*\.html)$#';

    private const string WITHOUT_SECTION = '#^/(\d{4})/(\d{2})/(\d{2})/([a-z0-9]+(?:-[a-z0-9]+)*)\.html$#';

    /**
     * The address of the post without its section, or null when the path
     * given is no address of a post.
     */
    public static function withoutSection(string $path): ?string
    {
        return 1 === preg_match(self::PATTERN, $path, $matches) ? $matches[1] : null;
    }

    /**
     * The address without a section of the post first published on the day
     * given, as Y-m-d, with the slug given.
     */
    public static function fromDayAndSlug(string $day, string $slug): string
    {
        return \sprintf('/%s/%s.html', str_replace('-', '/', $day), $slug);
    }

    /**
     * The day, as Y-m-d, and the slug of an address of a post without its
     * section, or null when it is none.
     *
     * @return ?array{day: string, slug: string}
     */
    public static function dayAndSlug(string $path): ?array
    {
        return 1 === preg_match(self::WITHOUT_SECTION, $path, $matches)
            ? ['day' => \sprintf('%s-%s-%s', $matches[1], $matches[2], $matches[3]), 'slug' => $matches[4]]
            : null;
    }
}
