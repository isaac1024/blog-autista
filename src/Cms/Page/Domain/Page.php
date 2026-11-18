<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Cms\Shared\Domain\PublicationTimeline;
use Cms\Shared\Domain\Seo;
use Cms\Shared\Domain\Slug;
use Cms\Shared\Domain\Slugger;
use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\AggregateRoot;
use Shared\Domain\Clock;
use Shared\Domain\Event\EventBus;
use Shared\Domain\RichText\BlockType;
use Shared\Domain\RichText\CalloutVariant;
use Shared\Domain\RichText\LinkedContents;
use Shared\Domain\RichText\Mark;
use Shared\Domain\RichText\RichText;
use Shared\Domain\RichText\RichTextFeatures;

/**
 * A CMS page, identified by its id and addressed by its slug, except the home
 * page, which is served at the root of the site and so has none. Any other
 * page may be saved without a slug, and needs one to be published.
 *
 * Editors work on its version in the CMS; once published, the site serves a
 * copy of it, which only changes when the page is published again. So a
 * published page can be edited, and its changes published or discarded,
 * without the site showing them halfway.
 *
 * - Editing saves whatever valid fields an editor writes, complete or not.
 * - A page is published only with everything the site shows: a slug, unless
 *   it is the home page, content and a meta description. Publishing is what
 *   checks it, so a published page can be saved without them, and the site
 *   keeps its version meanwhile.
 * - A published page is unpublished before it is deleted, and unique pages
 *   are never unpublished nor deleted. A page redirects lead to stays on
 *   the site while they do.
 * - A page links to other pages, never to itself, and to posts, sections,
 *   tags and authors; one that others link to is never deleted; it can be
 *   unpublished, and the site leaves its links as plain text. What the
 *   version on the site uses counts as used only while it is there.
 *
 * The unique indexes on the slugs keep two pages from sharing an address,
 * the one being edited or the one on the site; pages without one share none.
 * The one on the unique type keeps the site to one page of each unique type.
 *
 * Its rules reach every value object, error and event of the page, so its
 * coupling grows with what a page is rather than with how it is designed;
 * so do its methods, one per rule and per thing told of it, such as each of
 * its slugs, and the branches of those rules, which have to hold together as
 * the boundary of one page.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.TooManyMethods")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
#[ORM\Entity]
#[ORM\Table(name: 'cms_page')]
#[ORM\UniqueConstraint(name: 'uniq_cms_page_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_cms_page_published_slug', columns: ['published_slug'])]
#[ORM\UniqueConstraint(name: 'uniq_cms_page_unique_type', columns: ['unique_type'])]
#[ORM\Index(name: 'idx_cms_page_last_site_slug', columns: ['last_site_slug'])]
#[ORM\Index(name: 'ftx_cms_page_title', columns: ['title'], flags: ['fulltext'])]
final class Page extends AggregateRoot
{
    /**
     * The slug of the version on the site, apart from that version so it can
     * be indexed and kept unique. Null while the page isn't on the site, and
     * for the home page, which has no slug.
     */
    #[ORM\Column(name: 'published_slug', type: 'cms_slug', length: Slug::MAX_LENGTH, nullable: true)]
    private ?Slug $publishedSlug;

    /**
     * The slug the page last had on the site, kept once it is off it, so its
     * last address can be told and found. Null for a page never published,
     * for the home page, and once another page is published at that address.
     */
    #[ORM\Column(name: 'last_site_slug', type: 'cms_slug', length: Slug::MAX_LENGTH, nullable: true)]
    private ?Slug $lastSiteSlug;

    /**
     * The type of a unique page, apart from it so it can be indexed and kept
     * unique. Null for generic pages, of which there are any number.
     */
    #[ORM\Column(name: 'unique_type', length: 20, nullable: true, enumType: PageType::class)]
    private ?PageType $uniqueType;

    /**
     * One argument per mapped field: Doctrine hydrates it without calling
     * this, and the named constructors start each field.
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
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
        #[ORM\Embedded(class: PublicationTimeline::class, columnPrefix: false)]
        private PublicationTimeline $timeline,
        #[ORM\Column(options: ['default' => 0])]
        private int $publicationRevision,
    ) {
        $this->publishedSlug = PageStatus::Published === $status ? $published?->slug() : null;
        $this->lastSiteSlug = $published?->slug();
        $this->uniqueType = $type->isUnique() ? $type : null;
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
                new PageTitle($title),
                new Slug(new Slugger($title)()),
                RichText::empty(),
                Seo::initial(PageType::Standard->initialRobots()),
            ),
            PageStatus::Draft,
            null,
            PublicationTimeline::start(Clock::now()),
            0,
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
                new PageTitle($title),
                $type->isHome() ? null : new Slug(new Slugger($title)()),
                RichText::empty(),
                Seo::initial($type->initialRobots()),
            ),
            PageStatus::Draft,
            null,
            PublicationTimeline::start(Clock::now()),
            0,
        );
    }

    /**
     * What the content of a page can use: the blocks of fixed pages such as
     * the legal ones, with two heading levels, bold and links to other pages
     * and to posts, sections, tags and authors.
     */
    public static function contentFeatures(): RichTextFeatures
    {
        return new RichTextFeatures(
            blocks: BlockType::cases(),
            headingLevels: [2, 3],
            calloutVariants: [CalloutVariant::Note, CalloutVariant::Important],
            marks: [Mark::Bold, Mark::Link],
        );
    }

    /**
     * Replaces everything an editor writes: the title, the slug, the content
     * and how the page presents itself to search engines. The slug is no
     * longer derived from the title, so editors can keep a page's address when
     * they retitle it, and a blank one leaves the page without any, freeing
     * it for another page once it is off the site. The home page takes none.
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
            new PageTitle($title),
            $this->type->slugFrom($slug),
            RichText::fromArray($content),
            Seo::fromPrimitives($metaDescription, $robots),
        );
        self::contentFeatures()->assertAllows($version->content());

        if (\in_array($this->id->value(), $version->content()->pageIds(), true)) {
            throw InvalidPageContent::linksItself();
        }

        $this->changeResourcesUsed(fn () => $this->replaceVersion($version));
    }

    /**
     * Puts the version last saved on the site, for the first time or in place
     * of the one there, once it has everything the site shows.
     */
    public function publish(): void
    {
        $now = Clock::now();
        $this->version->assertPublishableAs($this->type);

        if (!$this->hasSomethingToPublish()) {
            throw InvalidPagePublication::nothingToPublish();
        }

        $this->changeResourcesUsed(function (): void {
            $this->status = PageStatus::Published;
            $this->published = $this->version;
        });
        $this->publishedSlug = $this->version->slug();
        $this->lastSiteSlug = $this->version->slug();
        $this->timeline = $this->timeline->published($now);
        ++$this->publicationRevision;

        $this->record(new PagePublished(
            $this->id->value(),
            $this->type->value,
            $this->version->title()->value(),
            $this->version->slug()?->value(),
            $this->version->content()->toArray(),
            (string) $this->version->seo()->description(),
            $this->version->seo()->robots()->value,
            ($this->timeline->firstPublishedAt() ?? $now)->format(\DATE_ATOM),
            $now->format(\DATE_ATOM),
            $this->publicationRevision,
        ));
    }

    /**
     * Takes the page off the site, freeing its address there. Unique pages
     * stay on the site once published, since other parts of it rely on them,
     * and so does a page that redirects lead to, until they lead elsewhere.
     */
    public function unpublish(PageRedirects $pageRedirects): void
    {
        if ($this->type->isUnique()) {
            throw InvalidPagePublication::uniquePage($this->type);
        }

        if (PageStatus::Published !== $this->status) {
            throw InvalidPagePublication::notPublished();
        }

        if ($pageRedirects->isTargeted($this->id)) {
            throw InvalidPagePublication::redirected();
        }

        // What it showed on the site is used no longer.
        $this->changeResourcesUsed(function (): void {
            $this->status = PageStatus::Unpublished;
        });
        $this->publishedSlug = null;
        ++$this->publicationRevision;

        $this->record(new PageUnpublished($this->id->value(), $this->publicationRevision));
    }

    /**
     * Forgets the address it last had on the site, once another page is
     * published there: it was the last of that other page from then on.
     */
    public function loseLastAddress(): void
    {
        if (PageStatus::Published === $this->status) {
            throw InvalidPagePublication::addressOnSite();
        }

        $this->lastSiteSlug = null;
    }

    /**
     * Brings the version being edited back to the one on the site.
     */
    public function discardChanges(): void
    {
        if (null === $this->published || !$this->hasUnpublishedChanges()) {
            throw InvalidPagePublication::noChangesToDiscard();
        }

        $published = $this->published;
        $this->changeResourcesUsed(fn () => $this->replaceVersion($published));
    }

    /**
     * Whether the page is on the site with a version other than the one being
     * edited, so its changes can be discarded.
     */
    public function hasUnpublishedChanges(): bool
    {
        return PageStatus::Published === $this->status
            && null !== $this->published
            && !$this->published->equals($this->version);
    }

    /**
     * Whether publishing has something to put on the site: the page isn't
     * there, or it is with another version. Whether that version has all the
     * site shows is only known by publishing it.
     */
    public function hasSomethingToPublish(): bool
    {
        return PageStatus::Published !== $this->status || $this->hasUnpublishedChanges();
    }

    /**
     * Whether it can be taken off the site: it is on it, and isn't unique.
     */
    public function canBeUnpublished(): bool
    {
        return !$this->type->isUnique() && PageStatus::Published === $this->status;
    }

    /**
     * Whether it can be deleted: it is off the site, and isn't unique.
     */
    public function canBeDeleted(): bool
    {
        return !$this->type->isUnique() && PageStatus::Published !== $this->status;
    }

    /**
     * Saves the page, then tells the rest of the system what happened to it
     * since it was loaded. It takes its events before saving, so the saved
     * page holds none, and none is told twice.
     */
    public function save(PageRepository $pageRepository, EventBus $eventBus): void
    {
        $events = $this->pullDomainEvents();
        $pageRepository->save($this);
        $eventBus->publish(...$events);
    }

    /**
     * Deletes a generic page off the site, unless other pages link to it;
     * unique pages stay for good.
     */
    public function delete(PageRepository $pageRepository, EventBus $eventBus, PageUsage $pageUsage): void
    {
        if ($this->type->isUnique()) {
            throw UniquePageCannotBeDeleted::ofType($this->type);
        }

        if (PageStatus::Published === $this->status) {
            throw InvalidPagePublication::publishedPage();
        }

        if ($pageUsage->isInUse($this->id)) {
            throw InvalidPagePublication::linkedPage();
        }

        // A page deleted embeds no image, nor links to any page, any longer.
        $used = $this->resourcesUsed();

        if ([] !== $used['images']) {
            $this->record(new PageImagesUsed($this->id->value(), []));
        }

        if ([] !== $used['pages']) {
            $this->record(new PagePagesLinked($this->id->value(), []));
        }

        if (!$this->contentsLinked()->isEmpty()) {
            $this->record(new PageContentsLinked($this->id->value(), LinkedContents::fromTexts()));
        }

        $events = $this->pullDomainEvents();
        $pageRepository->delete($this);
        $eventBus->publish(...$events);
    }

    public function id(): PageId
    {
        return $this->id;
    }

    public function type(): PageType
    {
        return $this->type;
    }

    public function title(): PageTitle
    {
        return $this->version->title();
    }

    /**
     * Null for the home page, and for any other page left without one.
     */
    public function slug(): ?Slug
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
     * The slug the page has on the site, null while it isn't there and for
     * the home page.
     */
    public function publishedSlug(): ?Slug
    {
        return $this->publishedSlug;
    }

    /**
     * The slug the page last had on the site, on it or not: null for a page
     * never published, for the home page, and for one whose last address
     * another page took.
     */
    public function lastSiteSlug(): ?Slug
    {
        return $this->lastSiteSlug;
    }

    /**
     * The version the site serves, null while the page isn't on it.
     */
    public function onSite(): ?PageVersion
    {
        return $this->status->versionOnSite($this->published);
    }

    public function timeline(): PublicationTimeline
    {
        return $this->timeline;
    }

    /**
     * Makes a change and, when it changes the images the page embeds or the
     * pages it links to, tells which it uses now.
     *
     * @param \Closure(): void $change
     */
    private function changeResourcesUsed(\Closure $change): void
    {
        $before = $this->resourcesUsed();
        $linkedBefore = $this->contentsLinked();
        $change();
        $after = $this->resourcesUsed();
        $linkedAfter = $this->contentsLinked();

        if (!$linkedBefore->equals($linkedAfter)) {
            $this->record(new PageContentsLinked($this->id->value(), $linkedAfter));
        }

        if ($before['images'] !== $after['images']) {
            $this->record(new PageImagesUsed($this->id->value(), $after['images']));
        }

        if ($before['pages'] !== $after['pages']) {
            $this->record(new PagePagesLinked($this->id->value(), $after['pages']));
        }
    }

    /**
     * The images embedded in, and the pages linked from, the version being
     * edited and the one on the site, each once and in order.
     *
     * @return array{images: list<string>, pages: list<string>}
     */
    private function resourcesUsed(): array
    {
        $images = [...$this->version->content()->imageIds(), ...($this->onSite()?->content()->imageIds() ?? [])];
        $pages = [...$this->version->content()->pageIds(), ...($this->onSite()?->content()->pageIds() ?? [])];
        sort($images);
        sort($pages);

        return ['images' => array_values(array_unique($images)), 'pages' => array_values(array_unique($pages))];
    }

    /**
     * The posts, sections and tags, and authors the version being edited
     * and the one on the site link to or show in widgets.
     */
    private function contentsLinked(): LinkedContents
    {
        return LinkedContents::fromTexts($this->version->content(), $this->onSite()?->content());
    }

    private function replaceVersion(PageVersion $version): void
    {
        if (!$version->equals($this->version)) {
            $this->timeline = $this->timeline->updated(Clock::now());
        }

        $this->version = $version;
    }
}
