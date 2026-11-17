<?php

declare(strict_types=1);

namespace Web\Sitemap\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * A file of the sitemap of the site: the pages, the sections, the tags, the
 * authors, or the posts first published in a year. It counts its entries,
 * and keeps when any of them last changed, which the index of the sitemap
 * tells search engines.
 */
#[ORM\Entity]
#[ORM\Table(name: 'web_sitemap_file')]
final class SitemapFile
{
    public const string PAGES = 'paginas';

    public const string SECTIONS = 'secciones';

    public const string TAGS = 'etiquetas';

    public const string AUTHORS = 'autores';

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 20)]
        private readonly string $name,
        #[ORM\Column]
        private int $entries,
        #[ORM\Column]
        private \DateTimeImmutable $lastmod,
    ) {}

    /**
     * The file of the posts first published in the year of the day given.
     */
    public static function postsOf(\DateTimeImmutable $day): string
    {
        return 'posts-'.$day->format('Y');
    }

    public static function open(string $name, \DateTimeImmutable $moment): self
    {
        return new self($name, 0, $moment);
    }

    public function added(\DateTimeImmutable $moment): void
    {
        ++$this->entries;
        $this->touched($moment);
    }

    /**
     * One of its entries left it, at the moment given: only an entry it has
     * is removed, so it never counts fewer than none.
     */
    public function removed(\DateTimeImmutable $moment): void
    {
        --$this->entries;
        $this->touched($moment);
    }

    /**
     * One of its entries changed at the moment given; it keeps the latest.
     */
    public function touched(\DateTimeImmutable $moment): void
    {
        $this->lastmod = max($this->lastmod, $moment);
    }

    public function isEmpty(): bool
    {
        return 0 === $this->entries;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function lastmod(): \DateTimeImmutable
    {
        return $this->lastmod;
    }
}
