<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Reading;

/**
 * What the head of a page of the web tells of it, through the tags of the
 * Open Graph protocol, those of Twitter cards, and plain HTML: its title, its
 * summary, its site, its image, when it was published and its language.
 * Read with the HTML parser of PHP itself, which runs nothing of the page.
 */
final readonly class PageMetadata
{
    /**
     * @param array<string, string> $meta the content of each meta tag, by its property or name, in lowercase; the first of each
     */
    private function __construct(
        private array $meta,
        private ?string $title,
        private ?string $language,
    ) {}

    public static function fromHtml(string $html): self
    {
        $document = \Dom\HTMLDocument::createFromString($html, \LIBXML_NOERROR | \LIBXML_COMPACT | \Dom\HTML_NO_DEFAULT_NS);
        $meta = [];

        foreach ($document->getElementsByTagName('meta') as $tag) {
            $name = strtolower(trim($tag->getAttribute('property') ?? $tag->getAttribute('name') ?? ''));
            $content = trim((string) $tag->getAttribute('content'));

            if ('' !== $name && '' !== $content && !\array_key_exists($name, $meta)) {
                $meta[$name] = $content;
            }
        }

        $title = $document->getElementsByTagName('title')->item(0)?->textContent;
        $language = $document->documentElement?->getAttribute('lang');

        return new self($meta, null === $title ? null : self::clean($title), null === $language ? null : trim($language));
    }

    public function title(): ?string
    {
        return $this->first('og:title', 'twitter:title') ?? $this->title;
    }

    public function summary(): ?string
    {
        return $this->first('og:description', 'twitter:description', 'description');
    }

    public function siteName(): ?string
    {
        return $this->first('og:site_name', 'application-name');
    }

    /**
     * The address of its image, absolute, resolved against the page's own.
     */
    public function image(string $pageUrl): ?string
    {
        $image = $this->first('og:image:secure_url', 'og:image', 'twitter:image', 'twitter:image:src');

        return null === $image ? null : self::absolute($image, $pageUrl);
    }

    /**
     * The day it was published, as Y-m-d, when it tells one.
     */
    public function publishedOn(): ?string
    {
        $published = $this->first('article:published_time', 'og:published_time', 'date', 'dc.date', 'citation_publication_date');

        if (null === $published || 1 !== preg_match('/^(\d{4}-\d{2}-\d{2})/', $published, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * The language it is written in, as its first two letters, such as "es".
     */
    public function language(): ?string
    {
        $language = $this->language ?? $this->first('og:locale', 'language', 'content-language');

        return null === $language || 1 !== preg_match('/^([a-z]{2})(?:[-_]|$)/i', $language, $matches) ? null : strtolower($matches[1]);
    }

    private function first(string ...$names): ?string
    {
        foreach ($names as $name) {
            if (isset($this->meta[$name])) {
                return self::clean($this->meta[$name]);
            }
        }

        return null;
    }

    private static function clean(string $value): ?string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8')));

        return '' === $value ? null : $value;
    }

    private static function absolute(string $url, string $pageUrl): ?string
    {
        if (1 === preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $page = parse_url($pageUrl);

        if (false === $page || !isset($page['scheme'], $page['host'])) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            return $page['scheme'].':'.$url;
        }

        $base = $page['scheme'].'://'.$page['host'].(isset($page['port']) ? ':'.$page['port'] : '');

        if (str_starts_with($url, '/')) {
            return $base.$url;
        }

        return $base.rtrim(\dirname($page['path'] ?? '/'), '/').'/'.$url;
    }
}
