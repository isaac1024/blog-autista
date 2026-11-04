<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Cms\Shared\Domain\Slugger;
use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\RichText\BlockType;
use Shared\Domain\RichText\CalloutVariant;
use Shared\Domain\RichText\Mark;
use Shared\Domain\RichText\RichText;
use Shared\Domain\RichText\RichTextFeatures;

/**
 * A CMS page, identified by its id and addressed by its slug, except the home
 * page, which is served at the root of the site.
 *
 * The unique index on the slug also keeps the site to one home page, since
 * that is the only page stored with an empty slug.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cms_page')]
#[ORM\UniqueConstraint(name: 'uniq_cms_page_slug', columns: ['slug'])]
#[ORM\Index(name: 'ftx_cms_page_title', columns: ['title'], flags: ['fulltext'])]
final class Page
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'cms_page_id')]
        private readonly PageId $id,
        #[ORM\Column(length: 20, enumType: PageType::class)]
        private readonly PageType $type,
        #[ORM\Embedded(class: Title::class, columnPrefix: false)]
        private Title $title,
        #[ORM\Column(type: 'cms_page_slug', length: Slug::MAX_LENGTH)]
        private PageSlug $slug,
        #[ORM\Column(type: 'rich_text')]
        private RichText $content,
        #[ORM\Embedded(class: Seo::class, columnPrefix: false)]
        private Seo $seo,
    ) {
        self::contentFeatures()->assertAllows($content);

        if ($type->isHome() && !$slug instanceof HomeSlug) {
            throw InvalidSlug::onHome($slug->value());
        }

        if (!$type->isHome() && $slug instanceof HomeSlug) {
            throw InvalidSlug::missing();
        }
    }

    /**
     * Initialises a new generic page from primitives, deriving its slug from
     * the title.
     */
    public static function create(string $id, string $title): self
    {
        return new self(
            new PageId($id),
            PageType::Standard,
            new Title($title),
            new Slug(new Slugger($title)()),
            RichText::empty(),
            Seo::initialFor(PageType::Standard),
        );
    }

    /**
     * Initialises one of the unique pages of the site, deriving its slug from
     * the title unless it is the home page.
     */
    public static function createUnique(string $id, PageType $type, string $title): self
    {
        if (!$type->isUnique()) {
            throw InvalidPageType::notUnique($type);
        }

        return new self(
            new PageId($id),
            $type,
            new Title($title),
            $type->isHome() ? new HomeSlug() : new Slug(new Slugger($title)()),
            RichText::empty(),
            Seo::initialFor($type),
        );
    }

    /**
     * What the content of a page can use: the blocks of fixed pages such as
     * the legal ones, with two heading levels, and bold as the only mark.
     */
    public static function contentFeatures(): RichTextFeatures
    {
        return new RichTextFeatures(
            blocks: BlockType::cases(),
            headingLevels: [2, 3],
            calloutVariants: [CalloutVariant::Note, CalloutVariant::Important],
            marks: [Mark::Bold],
        );
    }

    /**
     * Replaces everything an editor writes: the title, the slug, the content
     * and how the page presents itself to search engines. The slug is no
     * longer derived from the title, so editors can keep a page's address when
     * they retitle it. The home page takes an empty slug, and no other page
     * does, so no page becomes the home page, nor stops being it, by being
     * edited.
     *
     * @param array<array-key, mixed> $content in the stored rich text format
     */
    public function edit(
        string $title,
        string $slug,
        array $content,
        string $metaDescription,
        string $robots,
        string $canonicalUrl,
    ): void {
        // Everything is validated before anything is assigned, so an invalid
        // field doesn't leave the page with only part of the edit.
        $newTitle = new Title($title);
        $newSlug = $this->slugFrom($slug);
        $newContent = RichText::fromArray($content);
        self::contentFeatures()->assertAllows($newContent);
        $newSeo = Seo::fromPrimitives($metaDescription, $robots, $canonicalUrl);

        $this->title = $newTitle;
        $this->slug = $newSlug;
        $this->content = $newContent;
        $this->seo = $newSeo;
    }

    public function save(PageRepository $pageRepository): void
    {
        $pageRepository->save($this);
    }

    /**
     * Deletes a generic page; unique pages stay for good.
     */
    public function delete(PageRepository $pageRepository): void
    {
        if ($this->type->isUnique()) {
            throw UniquePageCannotBeDeleted::ofType($this->type);
        }

        $pageRepository->delete($this);
    }

    public function id(): PageId
    {
        return $this->id;
    }

    public function type(): PageType
    {
        return $this->type;
    }

    public function title(): Title
    {
        return $this->title;
    }

    public function slug(): PageSlug
    {
        return $this->slug;
    }

    public function content(): RichText
    {
        return $this->content;
    }

    public function seo(): Seo
    {
        return $this->seo;
    }

    private function slugFrom(string $slug): PageSlug
    {
        if (!$this->type->isHome()) {
            return new Slug($slug);
        }

        if ('' !== $slug) {
            throw InvalidSlug::onHome($slug);
        }

        return new HomeSlug();
    }
}
