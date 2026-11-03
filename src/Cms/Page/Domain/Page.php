<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Cms\Shared\Domain\Slugger;
use Doctrine\ORM\Mapping as ORM;

/**
 * A CMS page, identified by its id and addressed by its slug.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cms_page')]
#[ORM\Index(name: 'idx_cms_page_slug', columns: ['slug'])]
#[ORM\Index(name: 'ftx_cms_page_title', columns: ['title'], flags: ['fulltext'])]
final class Page
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'cms_page_id')]
        private readonly PageId $id,
        #[ORM\Embedded(class: Title::class, columnPrefix: false)]
        private Title $title,
        #[ORM\Embedded(class: Slug::class, columnPrefix: false)]
        private Slug $slug,
    ) {}

    /**
     * Initialises a new page from primitives, deriving its slug from the title.
     */
    public static function create(string $id, string $title): self
    {
        return new self(
            new PageId($id),
            new Title($title),
            new Slug(new Slugger($title)()),
        );
    }

    /**
     * Replaces the title and the slug; the slug is no longer derived from the
     * title, so editors can keep a page's address when they retitle it.
     */
    public function edit(string $title, string $slug): void
    {
        $this->title = new Title($title);
        $this->slug = new Slug($slug);
    }

    public function save(PageRepository $pageRepository): void
    {
        $pageRepository->save($this);
    }

    public function id(): PageId
    {
        return $this->id;
    }

    public function title(): Title
    {
        return $this->title;
    }

    public function slug(): Slug
    {
        return $this->slug;
    }
}
