<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Cms\Shared\Domain\Slugger;
use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\AggregateRoot;
use Shared\Domain\Clock;
use Shared\Domain\Event\EventId;
use Shared\Domain\RichText\BlockType;
use Shared\Domain\RichText\CalloutVariant;
use Shared\Domain\RichText\Mark;
use Shared\Domain\RichText\RichText;
use Shared\Domain\RichText\RichTextFeatures;

/**
 * A CMS page, identified by its id and addressed by its slug, except the home
 * page, which is served at the root of the site.
 *
 * Editors work on its version in the CMS; once published, the site serves a
 * copy of it, which only changes when the page is published again. So a
 * published page can be edited, and its changes published or discarded,
 * without the site showing them halfway.
 *
 * - A page starts as a draft, and is published only with everything the site
 *   shows: content and a meta description.
 * - A published page keeps both while it is published, so it can always be
 *   published again.
 * - A published page is unpublished before it is deleted, and unique pages
 *   are never unpublished nor deleted.
 *
 * The unique indexes on the slugs keep two pages from sharing an address,
 * the one being edited or the one on the site. They also keep the site to
 * one home page, since that is the only page stored with an empty slug.
 *
 * Its rules reach every value object, error and event of the page, so its
 * coupling grows with what a page is rather than with how it is designed.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
#[ORM\Entity]
#[ORM\Table(name: 'cms_page')]
#[ORM\UniqueConstraint(name: 'uniq_cms_page_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_cms_page_published_slug', columns: ['published_slug'])]
#[ORM\Index(name: 'ftx_cms_page_title', columns: ['title'], flags: ['fulltext'])]
final class Page extends AggregateRoot
{
    /**
     * The slug of the version on the site, apart from that version so it can
     * be indexed and kept unique. Null while the page isn't on the site.
     */
    #[ORM\Column(name: 'published_slug', type: 'cms_page_slug', length: Slug::MAX_LENGTH, nullable: true)]
    private ?PageSlug $publishedSlug;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'cms_page_id')]
        private readonly PageId $id,
        #[ORM\Column(length: 20, enumType: PageType::class)]
        private readonly PageType $type,
        #[ORM\Embedded(class: PageVersion::class, columnPrefix: false)]
        private PageVersion $version,
        #[ORM\Column(length: 20, enumType: PageStatus::class)]
        private PageStatus $status,
        /*
         * The version last published, kept when the page is unpublished.
         */
        #[ORM\Column(name: 'published_version', type: 'cms_page_version', nullable: true)]
        private ?PageVersion $published,
        #[ORM\Embedded(class: Timeline::class, columnPrefix: false)]
        private Timeline $timeline,
    ) {
        $this->publishedSlug = PageStatus::Published === $status ? $published?->slug() : null;
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
            new PageVersion(
                new Title($title),
                new Slug(new Slugger($title)()),
                RichText::empty(),
                Seo::initialFor(PageType::Standard),
            ),
            PageStatus::Draft,
            null,
            Timeline::start(Clock::now()),
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
            new PageVersion(
                new Title($title),
                $type->isHome() ? new HomeSlug() : new Slug(new Slugger($title)()),
                RichText::empty(),
                Seo::initialFor($type),
            ),
            PageStatus::Draft,
            null,
            Timeline::start(Clock::now()),
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
     * On a published page, it only changes the version being edited: the site
     * keeps its version until the page is published again.
     *
     * @param array<array-key, mixed> $content in the stored rich text format
     */
    public function edit(
        string $title,
        string $slug,
        array $content,
        string $metaDescription,
        string $robots,
    ): void {
        // Everything is validated before anything is assigned, so an invalid
        // field doesn't leave the page with only part of the edit.
        $version = new PageVersion(
            new Title($title),
            $this->slugFrom($slug),
            RichText::fromArray($content),
            Seo::fromPrimitives($metaDescription, $robots),
        );
        self::contentFeatures()->assertAllows($version->content());

        if (PageStatus::Published === $this->status) {
            $version->assertPublishable();
        }

        $this->replaceVersion($version);
    }

    /**
     * Puts the version being edited on the site, for the first time or in
     * place of the one there.
     */
    public function publish(): void
    {
        $now = Clock::now();
        $this->version->assertPublishable();

        if (PageStatus::Published === $this->status && !$this->hasUnpublishedChanges()) {
            throw InvalidPublication::nothingToPublish();
        }

        $this->status = PageStatus::Published;
        $this->published = $this->version;
        $this->publishedSlug = $this->version->slug();
        $this->timeline = $this->timeline->published($now);

        $this->record(new PagePublished(
            $this->id->value(),
            $this->type->value,
            $this->version->title()->value(),
            $this->version->slug()->value(),
            $this->version->content()->toArray(),
            (string) $this->version->seo()->description(),
            $this->version->seo()->robots()->value,
            ($this->timeline->firstPublishedAt() ?? $now)->format(\DATE_ATOM),
            $now->format(\DATE_ATOM),
            EventId::random()->value(),
            $now,
        ));
    }

    /**
     * Takes the page off the site, freeing its address there. Unique pages
     * stay on the site once published, since other parts of it rely on them.
     */
    public function unpublish(): void
    {
        if ($this->type->isUnique()) {
            throw InvalidPublication::uniquePage($this->type);
        }

        if (PageStatus::Published !== $this->status) {
            throw InvalidPublication::notPublished();
        }

        $this->status = PageStatus::Unpublished;
        $this->publishedSlug = null;

        $this->record(new PageUnpublished($this->id->value(), EventId::random()->value(), Clock::now()));
    }

    /**
     * Brings the version being edited back to the one on the site.
     */
    public function discardChanges(): void
    {
        if (null === $this->published || !$this->hasUnpublishedChanges()) {
            throw InvalidPublication::noChangesToDiscard();
        }

        $this->replaceVersion($this->published);
    }

    /**
     * Whether the page is on the site with a version other than the one being
     * edited.
     */
    public function hasUnpublishedChanges(): bool
    {
        return PageStatus::Published === $this->status
            && null !== $this->published
            && !$this->published->equals($this->version);
    }

    public function save(PageRepository $pageRepository): void
    {
        $pageRepository->save($this);
    }

    /**
     * Deletes a generic page off the site; unique pages stay for good.
     */
    public function delete(PageRepository $pageRepository): void
    {
        if ($this->type->isUnique()) {
            throw UniquePageCannotBeDeleted::ofType($this->type);
        }

        if (PageStatus::Published === $this->status) {
            throw InvalidPublication::publishedPage();
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
        return $this->version->title();
    }

    public function slug(): PageSlug
    {
        return $this->version->slug();
    }

    public function content(): RichText
    {
        return $this->version->content();
    }

    public function seo(): Seo
    {
        return $this->version->seo();
    }

    public function status(): PageStatus
    {
        return $this->status;
    }

    /**
     * The slug the page has on the site, null while it isn't there.
     */
    public function publishedSlug(): ?PageSlug
    {
        return $this->publishedSlug;
    }

    public function timeline(): Timeline
    {
        return $this->timeline;
    }

    private function replaceVersion(PageVersion $version): void
    {
        if (!$version->equals($this->version)) {
            $this->timeline = $this->timeline->updated(Clock::now());
        }

        $this->version = $version;
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
