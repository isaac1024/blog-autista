<?php

declare(strict_types=1);

namespace Web\Sitemap\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * An address of the site in its sitemap: a page, a section, a tag, an author
 * or a post that search engines may index, by the id of what it is, with
 * when it last changed. A post keeps the image it opens with, which the
 * sitemap names while it is on the site, and its section and tags, whose
 * lists change with it.
 *
 * Its constructor takes all of its state, one parameter each, as Doctrine maps it.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[ORM\Entity]
#[ORM\Table(name: 'web_sitemap_entry')]
#[ORM\Index(name: 'idx_web_sitemap_entry_file', columns: ['file', 'path'])]
#[ORM\Index(name: 'idx_web_sitemap_entry_image', columns: ['image_id'])]
final class SitemapEntry
{
    /**
     * @param list<string> $termIds the section and the tags of a post, none for anything else
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'guid')]
        private readonly string $resourceId,
        #[ORM\Column(length: 20)]
        private readonly string $file,
        #[ORM\Column(length: 255)]
        private string $path,
        #[ORM\Column]
        private \DateTimeImmutable $lastmod,
        #[ORM\Column(type: 'guid', nullable: true)]
        private ?string $imageId,
        #[ORM\Column(type: 'json')]
        private array $termIds,
    ) {}

    /**
     * Takes what it is now: where it is, when it changed, and for a post its
     * image and its terms. Its file never changes.
     *
     * @param list<string> $termIds
     */
    public function update(string $path, \DateTimeImmutable $lastmod, ?string $imageId, array $termIds): void
    {
        $this->path = $path;
        $this->lastmod = $lastmod;
        $this->imageId = $imageId;
        $this->termIds = $termIds;
    }

    /**
     * What it lists changed, as a section or a tag does when one of its
     * posts is published or taken off the site.
     */
    public function touch(\DateTimeImmutable $moment): void
    {
        $this->lastmod = max($this->lastmod, $moment);
    }

    public function file(): string
    {
        return $this->file;
    }

    /**
     * @return list<string>
     */
    public function termIds(): array
    {
        return $this->termIds;
    }
}
