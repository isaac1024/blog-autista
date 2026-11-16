<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Cms\Shared\Domain\Robots;
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
use Shared\Domain\RichText\LinkType;
use Shared\Domain\RichText\Mark;
use Shared\Domain\RichText\RichText;
use Shared\Domain\RichText\RichTextFeatures;

/**
 * A post of the blog, filed under a section, signed by an author and
 * labelled with any number of tags. The site serves it under the slug of its
 * section, on the day it was first published, by its own slug.
 *
 * Editors work on its version in the CMS; once published, the site serves a
 * copy of it, which only changes when the post is published again.
 *
 * - Editing saves whatever valid fields an editor writes, complete or not,
 *   as long as its section, author, tags and opening image are ones the
 *   CMS has.
 * - Its content never links to the post itself, nor shows it in a widget.
 * - A post is published only with everything the site shows: a slug, a
 *   section, an author and an opening image, all three on the site,
 *   content and a meta description. Its tags needn't be: the site only
 *   shows those on it.
 * - Its address on the site is taken as it is published: the slug its
 *   section has on the site then, the day it was first published, and its
 *   own slug. Two posts on the site never share one; drafts take none, so
 *   they are never checked against each other.
 * - A post anything links to is never deleted.
 * - A published post is unpublished before it is deleted, and stays on
 *   the site while redirects lead to it.
 * - Once off the site, it keeps the last address it had there, until
 *   another post is published at it.
 * - The sections, tags and author it uses, the images it opens with and
 *   embeds, and the pages it links to are told to whoever keeps track of what is in use, so
 *   none is deleted while the post uses it: those of the version being
 *   edited, and those of the one on the site while it is there.
 *
 * Its rules reach every value object, error and event of the post, so its
 * coupling, its methods and its length grow with what a post is.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.ExcessiveClassLength")
 * @SuppressWarnings("PHPMD.TooManyMethods")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
#[ORM\Entity]
#[ORM\Table(name: 'cms_post')]
#[ORM\UniqueConstraint(name: 'uniq_cms_post_published_address', columns: ['published_section_slug', 'published_day', 'published_slug'])]
#[ORM\Index(name: 'idx_cms_post_last_address', columns: ['last_section_slug', 'last_day', 'last_slug'])]
#[ORM\Index(name: 'ftx_cms_post_title', columns: ['title'], flags: ['fulltext'])]
final class Post extends AggregateRoot
{
    /**
     * The slug of its section where the site serves the post, apart from the
     * version so the address can be indexed and kept unique. Null, as the
     * rest of the address, while the post isn't on the site.
     */
    #[ORM\Column(name: 'published_section_slug', type: 'cms_slug', length: Slug::MAX_LENGTH, nullable: true)]
    private ?Slug $publishedSectionSlug;

    /**
     * The day it was first published, in Madrid, while it is on the site.
     */
    #[ORM\Column(name: 'published_day', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedDay;

    /**
     * Its own slug where the site serves it, while it is on the site.
     */
    #[ORM\Column(name: 'published_slug', type: 'cms_slug', length: Slug::MAX_LENGTH, nullable: true)]
    private ?Slug $publishedSlug;

    /**
     * The address the post last had on the site, as its three columns, kept
     * once it is off it, so it can be told and found. Null for a post never
     * published, and once another post is published at that address.
     */
    #[ORM\Column(name: 'last_section_slug', type: 'cms_slug', length: Slug::MAX_LENGTH, nullable: true)]
    private ?Slug $lastSectionSlug;

    #[ORM\Column(name: 'last_day', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastDay;

    #[ORM\Column(name: 'last_slug', type: 'cms_slug', length: Slug::MAX_LENGTH, nullable: true)]
    private ?Slug $lastSlug;

    /**
     * One argument per mapped field, the address standing for the columns
     * of both the address on the site and the last one: Doctrine hydrates it
     * without calling this, and the named constructor starts each field. The
     * address given is the one it has on the site, or last had there; a post
     * off the site has none there, whatever is given.
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'cms_post_id')]
        private readonly PostId $id,
        #[ORM\Embedded(class: PostVersion::class, columnPrefix: false)]
        private PostVersion $version,
        #[ORM\Column(length: 20, enumType: PostStatus::class)]
        private PostStatus $status,
        /*
         * The version last published, kept when the post is unpublished.
         */
        #[ORM\Column(name: 'published_version', type: 'cms_post_version', nullable: true)]
        private ?PostVersion $published,
        #[ORM\Embedded(class: Timeline::class, columnPrefix: false)]
        private Timeline $timeline,
        #[ORM\Column]
        private int $publicationRevision,
        ?PostAddress $address,
    ) {
        $onSite = PostStatus::Published === $status ? $address : null;
        $this->publishedSectionSlug = $onSite?->section();
        $this->publishedDay = null === $onSite ? null : new \DateTimeImmutable($onSite->day());
        $this->publishedSlug = $onSite?->slug();
        $this->lastSectionSlug = $address?->section();
        $this->lastDay = null === $address ? null : new \DateTimeImmutable($address->day());
        $this->lastSlug = $address?->slug();
    }

    /**
     * Initialises a new post from its title, deriving its slug from it. It
     * starts unfiled and unsigned, without an opening image or content,
     * listed in search results.
     */
    public static function create(string $id, string $title): self
    {
        return new self(
            new PostId($id),
            new PostVersion(
                new PostTitle($title),
                new Slug(new Slugger($title)()),
                Classification::none(),
                null,
                RichText::empty(),
                Seo::initial(Robots::IndexFollow),
            ),
            PostStatus::Draft,
            null,
            Timeline::start(Clock::now()),
            0,
            null,
        );
    }

    /**
     * What the content of a post can use: what the content of a page can,
     * with two heading levels, bold and links to pages, other posts,
     * sections, tags and authors.
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
     * Replaces everything an editor writes: the title, the slug, the section,
     * the author and the tags, the opening image, the content and how the
     * post presents itself to search engines. A blank slug, section, author
     * or opening image leaves the post without one.
     *
     * On a published post, it only changes the version being edited: the
     * site keeps its version until the post is published again.
     *
     * @param list<string>            $tags    ids of its tags, in order
     * @param array<array-key, mixed> $content in the stored rich text format
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function edit(
        string $title,
        string $slug,
        string $section,
        string $author,
        array $tags,
        string $openingImage,
        array $content,
        string $metaDescription,
        string $robots,
        Classifiers $classifiers,
        PostImages $postImages,
    ): void {
        // Everything is validated before anything is assigned, so an invalid
        // field doesn't leave the post with only part of the edit.
        $version = new PostVersion(
            new PostTitle($title),
            '' === $slug ? null : new Slug($slug),
            Classification::fromPrimitives($section, $author, $tags),
            '' === $openingImage ? null : new ImageId($openingImage),
            RichText::fromArray($content),
            Seo::fromPrimitives($metaDescription, $robots),
        );
        self::contentFeatures()->assertAllows($version->content());

        if (\in_array($this->id->value(), $version->content()->linkedIds(LinkType::Post), true)) {
            throw InvalidPostContent::linksItself();
        }

        $version->classification()->assertKnownTo($classifiers);
        $openingImageId = $version->openingImage();

        if (null !== $openingImageId && !$postImages->exists($openingImageId)) {
            throw InvalidOpeningImage::unknown($openingImageId);
        }

        $this->changeResourcesUsed(fn () => $this->replaceVersion($version));
    }

    /**
     * Puts the version last saved on the site, for the first time or in place
     * of the one there, once it has everything the site shows and its
     * section, author and opening image are on the site. It takes its address there then;
     * whether another post has it is checked apart, by PostAddressAvailability.
     */
    public function publish(Classifiers $classifiers, PostImages $postImages): void
    {
        $now = Clock::now();
        $this->version->assertPublishable();

        if (!$this->hasSomethingToPublish()) {
            throw InvalidPostPublication::nothingToPublish();
        }

        $classification = $this->version->classification();
        $sectionId = $classification->section() ?? throw InvalidPostPublication::withoutSection();
        $authorId = $classification->author() ?? throw InvalidPostPublication::withoutAuthor();
        $section = $classifiers->sectionOnSite($sectionId) ?? throw InvalidPostPublication::sectionOffSite();
        $author = $classifiers->authorOnSite($authorId) ?? throw InvalidPostPublication::authorOffSite();
        $openingImage = $this->version->openingImage() ?? throw InvalidPostPublication::withoutOpeningImage();

        if (!$postImages->isOnSite($openingImage)) {
            throw InvalidPostPublication::openingImageOffSite();
        }

        $slug = $this->version->slug() ?? throw InvalidPostPublication::withoutSlug();

        $timeline = $this->timeline->published($now);
        $address = new PostAddress($section->slug(), $timeline->firstPublishedAt() ?? $now, $slug);

        $this->changeResourcesUsed(function (): void {
            $this->status = PostStatus::Published;
            $this->published = $this->version;
        });
        $this->publishedSectionSlug = $address->section();
        $this->publishedDay = new \DateTimeImmutable($address->day());
        $this->publishedSlug = $address->slug();
        $this->lastSectionSlug = $address->section();
        $this->lastDay = new \DateTimeImmutable($address->day());
        $this->lastSlug = $address->slug();
        $this->timeline = $timeline;
        ++$this->publicationRevision;

        $this->record(new PostPublished(
            $this->id->value(),
            $this->version->title()->value(),
            $slug->value(),
            $sectionId->value(),
            $section->name(),
            $section->slug()->value(),
            $authorId->value(),
            $author->name(),
            $classification->tags()->values(),
            $openingImage->value(),
            $this->version->content()->toArray(),
            (string) $this->version->seo()->description(),
            $this->version->seo()->robots()->value,
            $address->day(),
            ($timeline->firstPublishedAt() ?? $now)->format(\DATE_ATOM),
            $now->format(\DATE_ATOM),
            $this->publicationRevision,
        ));
    }

    /**
     * Takes the post off the site, freeing its address there, unless
     * redirects lead to it: it stays on the site until they lead elsewhere.
     */
    public function unpublish(PostRedirects $postRedirects): void
    {
        if (PostStatus::Published !== $this->status) {
            throw InvalidPostPublication::notPublished();
        }

        if ($postRedirects->isTargeted($this->id)) {
            throw InvalidPostPublication::redirected();
        }

        // What it showed on the site is used no longer.
        $this->changeResourcesUsed(function (): void {
            $this->status = PostStatus::Unpublished;
        });
        $this->publishedSectionSlug = null;
        $this->publishedDay = null;
        $this->publishedSlug = null;
        ++$this->publicationRevision;

        $this->record(new PostUnpublished($this->id->value(), $this->publicationRevision));
    }

    /**
     * Forgets the address it last had on the site, once another post is
     * published there: it was the last of that other post from then on.
     */
    public function loseLastAddress(): void
    {
        if (PostStatus::Published === $this->status) {
            throw InvalidPostPublication::addressOnSite();
        }

        $this->lastSectionSlug = null;
        $this->lastDay = null;
        $this->lastSlug = null;
    }

    /**
     * Brings the version being edited back to the one on the site.
     */
    public function discardChanges(): void
    {
        if (null === $this->published || !$this->hasUnpublishedChanges()) {
            throw InvalidPostPublication::noChangesToDiscard();
        }

        $published = $this->published;
        $this->changeResourcesUsed(fn () => $this->replaceVersion($published));
    }

    /**
     * Whether the post is on the site with a version other than the one
     * being edited, so its changes can be discarded.
     */
    public function hasUnpublishedChanges(): bool
    {
        return PostStatus::Published === $this->status
            && null !== $this->published
            && !$this->published->equals($this->version);
    }

    /**
     * Whether publishing has something to put on the site: the post isn't
     * there, or it is with another version.
     */
    public function hasSomethingToPublish(): bool
    {
        return PostStatus::Published !== $this->status || $this->hasUnpublishedChanges();
    }

    public function canBeUnpublished(): bool
    {
        return PostStatus::Published === $this->status;
    }

    public function canBeDeleted(): bool
    {
        return PostStatus::Published !== $this->status;
    }

    /**
     * Saves the post, then tells the rest of the system what happened to it
     * since it was loaded. It takes its events before saving, so the saved
     * post holds none, and none is told twice.
     */
    public function save(PostRepository $postRepository, EventBus $eventBus): void
    {
        $events = $this->pullDomainEvents();
        $postRepository->save($this);
        $eventBus->publish(...$events);
    }

    /**
     * Deletes a post off the site that nothing links to.
     */
    public function delete(PostRepository $postRepository, EventBus $eventBus, PostUsage $postUsage): void
    {
        if (PostStatus::Published === $this->status) {
            throw InvalidPostPublication::publishedPost();
        }

        if ($postUsage->isInUse($this->id)) {
            throw InvalidPostPublication::usedPost();
        }

        // A post deleted uses nothing any longer.
        $this->recordResourcesUsed($this->resourcesUsed(), ['images' => [], 'pages' => [], 'posts' => [], 'terms' => [], 'authors' => [], 'externals' => []]);

        $events = $this->pullDomainEvents();
        $postRepository->delete($this);
        $eventBus->publish(...$events);
    }

    public function id(): PostId
    {
        return $this->id;
    }

    public function title(): PostTitle
    {
        return $this->version->title();
    }

    /**
     * Null while the post is left without one.
     */
    public function slug(): ?Slug
    {
        return $this->version->slug();
    }

    public function classification(): Classification
    {
        return $this->version->classification();
    }

    /**
     * Null while the post is left without one.
     */
    public function openingImage(): ?ImageId
    {
        return $this->version->openingImage();
    }

    public function content(): RichText
    {
        return $this->version->content();
    }

    public function seo(): Seo
    {
        return $this->version->seo();
    }

    public function status(): PostStatus
    {
        return $this->status;
    }

    /**
     * Where the site serves the post, null while it isn't there.
     */
    public function publishedAddress(): ?PostAddress
    {
        if (null === $this->publishedSectionSlug || null === $this->publishedDay || null === $this->publishedSlug) {
            return null;
        }

        return new PostAddress(
            $this->publishedSectionSlug,
            new \DateTimeImmutable($this->publishedDay->format('Y-m-d'), new \DateTimeZone(PostAddress::TIME_ZONE)),
            $this->publishedSlug,
        );
    }

    /**
     * The version on the site, null while the post is off it.
     */
    public function onSite(): ?PostVersion
    {
        return PostStatus::Published === $this->status ? $this->published : null;
    }

    /**
     * Where the site serves the post, or last served it once off it: null
     * for a post never published, and for one whose last address another
     * took.
     */
    public function lastAddress(): ?PostAddress
    {
        if (null === $this->lastSectionSlug || null === $this->lastDay || null === $this->lastSlug) {
            return null;
        }

        return new PostAddress(
            $this->lastSectionSlug,
            new \DateTimeImmutable($this->lastDay->format('Y-m-d'), new \DateTimeZone(PostAddress::TIME_ZONE)),
            $this->lastSlug,
        );
    }

    /**
     * Where the site would serve the post were it published now as it is
     * saved, as publish() takes it: under the slug its section has on the
     * site, on the day it was first published, or today, by its own slug.
     * Null when it has no such address, missing a slug or a section on the
     * site, and so can't be published.
     */
    public function nextAddress(Classifiers $classifiers): ?PostAddress
    {
        $sectionId = $this->version->classification()->section();
        $section = null === $sectionId ? null : $classifiers->sectionOnSite($sectionId);
        $slug = $this->version->slug();

        if (null === $section || null === $slug) {
            return null;
        }

        return new PostAddress($section->slug(), $this->timeline->firstPublishedAt() ?? Clock::now(), $slug);
    }

    public function timeline(): Timeline
    {
        return $this->timeline;
    }

    /**
     * Makes a change and, when it changes anything the post uses, tells what
     * it uses now.
     *
     * @param \Closure(): void $change
     */
    private function changeResourcesUsed(\Closure $change): void
    {
        $before = $this->resourcesUsed();
        $change();
        $this->recordResourcesUsed($before, $this->resourcesUsed());
    }

    /**
     * @param array{images: list<string>, pages: list<string>, posts: list<string>, terms: list<string>, authors: list<string>, externals: list<string>} $before
     * @param array{images: list<string>, pages: list<string>, posts: list<string>, terms: list<string>, authors: list<string>, externals: list<string>} $after
     */
    private function recordResourcesUsed(array $before, array $after): void
    {
        if ($before['images'] !== $after['images']) {
            $this->record(new PostImagesUsed($this->id->value(), $after['images']));
        }

        if ($before['pages'] !== $after['pages']) {
            $this->record(new PostPagesLinked($this->id->value(), $after['pages']));
        }

        if ($before['posts'] !== $after['posts']) {
            $this->record(new PostPostsLinked($this->id->value(), $after['posts']));
        }

        if ($before['terms'] !== $after['terms']) {
            $this->record(new PostTermsUsed($this->id->value(), $after['terms']));
        }

        if ($before['authors'] !== $after['authors']) {
            $this->record(new PostAuthorsUsed($this->id->value(), $after['authors']));
        }

        if ($before['externals'] !== $after['externals']) {
            $this->record(new PostExternalsLinked($this->id->value(), $after['externals']));
        }
    }

    /**
     * What the version being edited and the one on the site use, each
     * once and in order: the images it opens with and embeds, the pages and posts linked,
     * the section and the tags filed under or linked, the author signed by
     * or linked, and the external resources linked.
     *
     * @return array{images: list<string>, pages: list<string>, posts: list<string>, terms: list<string>, authors: list<string>, externals: list<string>}
     */
    private function resourcesUsed(): array
    {
        $images = [];
        $pages = [];
        $posts = [];
        $terms = [];
        $authors = [];
        $externals = [];

        foreach ([$this->version, $this->onSite()] as $version) {
            if (null === $version) {
                continue;
            }

            $classification = $version->classification();
            $images = [...$images, ...$version->imageIds()];
            $pages = [...$pages, ...$version->content()->pageIds()];
            $linked = LinkedContents::fromTexts($version->content());
            $posts = [...$posts, ...$linked->posts()];
            // What it is filed under and signed by, and what its content links to, alike.
            $terms = [...$terms, ...$classification->tags()->values(), ...$linked->terms()];
            $authors = [...$authors, ...$linked->authors()];
            $externals = [...$externals, ...$linked->externals()];

            if (null !== $classification->section()) {
                $terms[] = $classification->section()->value();
            }

            if (null !== $classification->author()) {
                $authors[] = $classification->author()->value();
            }
        }

        return [
            'images' => self::eachOnce($images),
            'pages' => self::eachOnce($pages),
            'posts' => self::eachOnce($posts),
            'terms' => self::eachOnce($terms),
            'authors' => self::eachOnce($authors),
            'externals' => self::eachOnce($externals),
        ];
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private static function eachOnce(array $ids): array
    {
        sort($ids);

        return array_values(array_unique($ids));
    }

    private function replaceVersion(PostVersion $version): void
    {
        if (!$version->equals($this->version)) {
            $this->timeline = $this->timeline->updated(Clock::now());
        }

        $this->version = $version;
    }
}
