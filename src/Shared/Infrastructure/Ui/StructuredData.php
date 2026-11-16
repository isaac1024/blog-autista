<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

/**
 * The nodes of schema.org a page of the site tells search engines it is, as
 * JSON-LD: the site, who publishes it, and what each page is, such as a
 * post or the profile of an author. Nodes name each other by their @id, the
 * address of what they are with a fragment of their kind, so the same
 * person, signing a post and publishing the blog, is one node.
 *
 * What a node doesn't know is left out, never written empty.
 */
final class StructuredData
{
    /**
     * The language every page of the site is written in.
     */
    public const string LANGUAGE = 'es';

    /**
     * @param string  $home      the absolute address of the home page
     * @param ?string $publisher the absolute address of the page of the author who publishes it, null for none
     *
     * @return array<string, mixed>
     */
    public static function website(string $home, string $name, ?string $publisher): array
    {
        return self::known([
            '@type' => 'WebSite',
            '@id' => self::websiteId($home),
            'url' => $home,
            'name' => $name,
            'inLanguage' => self::LANGUAGE,
            'publisher' => null === $publisher ? null : ['@id' => self::personId($publisher)],
        ]);
    }

    /**
     * An author, by the absolute address of their page.
     *
     * @param ?string $image the absolute address of their avatar
     *
     * @return array<string, mixed>
     */
    public static function person(string $url, string $name, ?string $image, ?string $description): array
    {
        return self::known([
            '@type' => 'Person',
            '@id' => self::personId($url),
            'url' => $url,
            'name' => $name,
            'image' => $image,
            'description' => $description,
        ]);
    }

    /**
     * A page of the site, of the kind given, such as ContactPage, at its
     * canonical address.
     *
     * @param array<string, mixed> $more what its kind tells besides, such as its main entity
     *
     * @return array<string, mixed>
     */
    public static function webPage(string $type, string $home, string $canonical, string $name, ?string $description, array $more): array
    {
        return self::known([
            '@type' => $type,
            '@id' => self::webPageId($canonical),
            'url' => $canonical,
            'name' => $name,
            'description' => $description,
            'isPartOf' => ['@id' => self::websiteId($home)],
            'inLanguage' => self::LANGUAGE,
            ...$more,
        ]);
    }

    /**
     * A post, the main entity of the page at its canonical address, signed
     * by its author: a person with a page while it is on the site, else just
     * the name the post kept.
     *
     * @param array{name: string, url: ?string} $author
     * @param ?string                           $image     the absolute address of the image it opens with
     * @param list<string>                      $keywords  the names of its tags
     * @param ?string                           $publisher the absolute address of the page of the author who publishes the blog
     *
     * @return array<string, mixed>
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public static function blogPosting(
        string $canonical,
        string $headline,
        ?string $description,
        ?string $image,
        \DateTimeImmutable $published,
        \DateTimeImmutable $modified,
        array $author,
        string $section,
        array $keywords,
        ?string $publisher,
    ): array {
        return self::known([
            '@type' => 'BlogPosting',
            '@id' => $canonical.'#article',
            'mainEntityOfPage' => ['@id' => self::webPageId($canonical)],
            'headline' => $headline,
            'description' => $description,
            'image' => $image,
            'datePublished' => $published->format(\DATE_ATOM),
            'dateModified' => $modified->format(\DATE_ATOM),
            'author' => null === $author['url'] ? ['@type' => 'Person', 'name' => $author['name']] : ['@id' => self::personId($author['url'])],
            'publisher' => null === $publisher ? null : ['@id' => self::personId($publisher)],
            'articleSection' => $section,
            'keywords' => [] === $keywords ? null : implode(', ', $keywords),
            'inLanguage' => self::LANGUAGE,
        ]);
    }

    /**
     * The absolute address of a path of the site, such as that of an image.
     */
    public static function absolute(string $home, string $path): string
    {
        return rtrim($home, '/').$path;
    }

    public static function personId(string $url): string
    {
        return $url.'#person';
    }

    private static function websiteId(string $home): string
    {
        return $home.'#website';
    }

    private static function webPageId(string $canonical): string
    {
        return $canonical.'#webpage';
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private static function known(array $node): array
    {
        return array_filter($node, static fn (mixed $value): bool => null !== $value);
    }
}
