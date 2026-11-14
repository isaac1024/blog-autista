<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

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
use Shared\Domain\RichText\Mark;
use Shared\Domain\RichText\RichText;
use Shared\Domain\RichText\RichTextFeatures;

/**
 * An author of the blog, the name posts are signed with, which needn't be
 * anyone who signs in to the CMS. Identified by its id and addressed by its
 * slug, it has a page of its own on the site: its avatar, its name and its
 * bio.
 *
 * Editors work on its version in the CMS; once published, the site serves a
 * copy of it, which only changes when the author is published again.
 *
 * - Editing saves whatever valid fields an editor writes, complete or not.
 * - An author is published only with everything the site shows: a slug, an
 *   avatar, a bio and a meta description.
 * - A published author is unpublished before it is deleted, and stays on
 *   the site while redirects lead to it.
 * - Once off the site, it keeps the last address it had there, until
 *   another author is published at it.
 * - The bio embeds images of the library and links to pages, which are told
 *   to whoever keeps track of what is in use, so neither is deleted while
 *   the bio uses it.
 * - Each avatar uploaded is a file of its own. The one being edited and the
 *   one on the site are kept while either version has it, and removed once
 *   neither does.
 *
 * Its rules reach every value object, error and event of the author, so its
 * coupling and its methods grow with what an author is.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.TooManyMethods")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
#[ORM\Entity]
#[ORM\Table(name: 'cms_author')]
#[ORM\UniqueConstraint(name: 'uniq_cms_author_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_cms_author_published_slug', columns: ['published_slug'])]
#[ORM\Index(name: 'idx_cms_author_last_site_slug', columns: ['last_site_slug'])]
final class Author extends AggregateRoot
{
    /**
     * The slug of the version on the site, apart from that version so it can
     * be indexed and kept unique. Null while the author isn't on the site.
     */
    #[ORM\Column(name: 'published_slug', type: 'cms_slug', length: Slug::MAX_LENGTH, nullable: true)]
    private ?Slug $publishedSlug;

    /**
     * The slug the author last had on the site, kept once it is off it, so
     * its last address can be told and found. Null for an author never
     * published, and once another author is published at that address.
     */
    #[ORM\Column(name: 'last_site_slug', type: 'cms_slug', length: Slug::MAX_LENGTH, nullable: true)]
    private ?Slug $lastSiteSlug;

    /**
     * Files of avatars kept since the author was loaded, removed again if
     * saving it fails. Not persisted.
     *
     * @var list<AvatarPath>
     */
    private array $keptAvatars = [];

    /**
     * Files of avatars neither version has any longer, removed once the
     * author is saved. Not persisted.
     *
     * @var list<AvatarPath>
     */
    private array $leftAvatars = [];

    /**
     * One argument per mapped field: Doctrine hydrates it without calling
     * this, and the named constructor starts each field.
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'cms_author_id')]
        private readonly AuthorId $id,
        #[ORM\Embedded(class: AuthorVersion::class, columnPrefix: false)]
        private AuthorVersion $version,
        #[ORM\Column(length: 20, enumType: AuthorStatus::class)]
        private AuthorStatus $status,
        /*
         * The version last published, kept when the author is unpublished.
         */
        #[ORM\Column(name: 'published_version', type: 'cms_author_version', nullable: true)]
        private ?AuthorVersion $published,
        #[ORM\Embedded(class: Timeline::class, columnPrefix: false)]
        private Timeline $timeline,
        #[ORM\Column]
        private int $publicationRevision,
    ) {
        $this->publishedSlug = AuthorStatus::Published === $status ? $published?->slug() : null;
        $this->lastSiteSlug = $published?->slug();
    }

    /**
     * Initialises a new author from its name, deriving its slug from it. It
     * starts without an avatar nor a bio, listed in search results.
     */
    public static function create(string $id, string $name): self
    {
        return new self(
            new AuthorId($id),
            new AuthorVersion(
                new AuthorName($name),
                new Slug(new Slugger($name)()),
                null,
                RichText::empty(),
                Seo::initial(Robots::IndexFollow),
            ),
            AuthorStatus::Draft,
            null,
            Timeline::start(Clock::now()),
            0,
        );
    }

    /**
     * What a bio can use: everything the content of a page can, with two
     * heading levels, bold and links to pages.
     */
    public static function bioFeatures(): RichTextFeatures
    {
        return new RichTextFeatures(
            blocks: BlockType::cases(),
            headingLevels: [2, 3],
            calloutVariants: [CalloutVariant::Note, CalloutVariant::Important],
            marks: [Mark::Bold, Mark::Link],
        );
    }

    /**
     * Replaces everything an editor writes but the avatar: the name, the
     * slug, the bio and how the author's page presents itself to search
     * engines. A blank slug leaves the author without any.
     *
     * On a published author, it only changes the version being edited: the
     * site keeps its version until the author is published again.
     *
     * @param array<array-key, mixed> $bio in the stored rich text format
     */
    public function edit(string $name, string $slug, array $bio, string $metaDescription, string $robots): void
    {
        // Everything is validated before anything is assigned, so an invalid
        // field doesn't leave the author with only part of the edit.
        $version = new AuthorVersion(
            new AuthorName($name),
            '' === $slug ? null : new Slug($slug),
            $this->version->avatar(),
            RichText::fromArray($bio),
            Seo::fromPrimitives($metaDescription, $robots),
        );
        self::bioFeatures()->assertAllows($version->bio());

        $this->change(fn () => $this->replaceVersion($version));
    }

    /**
     * Keeps the file as the avatar of the version being edited, in place of
     * any other. The file is kept at once, under a name of its own, and
     * removed again if saving the author fails.
     */
    public function replaceAvatar(AvatarFile $file, AvatarStorage $avatarStorage): void
    {
        $avatar = AvatarPath::random();
        $avatarStorage->save($avatar, $file);
        $this->keptAvatars[] = $avatar;

        $this->change(fn () => $this->replaceVersion($this->version->withAvatar($avatar)));
    }

    /**
     * Puts the version last saved on the site, for the first time or in place
     * of the one there, once it has everything the site shows.
     */
    public function publish(): void
    {
        $now = Clock::now();
        $this->version->assertPublishable();

        if (!$this->hasSomethingToPublish()) {
            throw InvalidAuthorPublication::nothingToPublish();
        }

        $this->status = AuthorStatus::Published;
        $this->change(function (): void {
            $this->published = $this->version;
        });
        $this->publishedSlug = $this->version->slug();
        $this->lastSiteSlug = $this->version->slug();
        $this->timeline = $this->timeline->published($now);
        ++$this->publicationRevision;

        $this->record(new AuthorPublished(
            $this->id->value(),
            $this->version->name()->value(),
            (string) $this->version->slug()?->value(),
            (string) $this->version->avatar()?->value(),
            $this->version->bio()->toArray(),
            (string) $this->version->seo()->description(),
            $this->version->seo()->robots()->value,
            ($this->timeline->firstPublishedAt() ?? $now)->format(\DATE_ATOM),
            $now->format(\DATE_ATOM),
            $this->publicationRevision,
        ));
    }

    /**
     * Takes the author off the site, freeing its address there, unless
     * redirects lead to it: it stays on the site until they lead elsewhere.
     */
    public function unpublish(AuthorRedirects $authorRedirects): void
    {
        if (AuthorStatus::Published !== $this->status) {
            throw InvalidAuthorPublication::notPublished();
        }

        if ($authorRedirects->isTargeted($this->id)) {
            throw InvalidAuthorPublication::redirected();
        }

        $this->status = AuthorStatus::Unpublished;
        $this->publishedSlug = null;
        ++$this->publicationRevision;

        $this->record(new AuthorUnpublished($this->id->value(), $this->publicationRevision));
    }

    /**
     * Forgets the address it last had on the site, once another author is
     * published there: it was the last of that other author from then on.
     */
    public function loseLastAddress(): void
    {
        if (AuthorStatus::Published === $this->status) {
            throw InvalidAuthorPublication::addressOnSite();
        }

        $this->lastSiteSlug = null;
    }

    /**
     * Brings the version being edited back to the one on the site.
     */
    public function discardChanges(): void
    {
        if (null === $this->published || !$this->hasUnpublishedChanges()) {
            throw InvalidAuthorPublication::noChangesToDiscard();
        }

        $published = $this->published;
        $this->change(fn () => $this->replaceVersion($published));
    }

    /**
     * Whether the author is on the site with a version other than the one
     * being edited, so its changes can be discarded.
     */
    public function hasUnpublishedChanges(): bool
    {
        return AuthorStatus::Published === $this->status
            && null !== $this->published
            && !$this->published->equals($this->version);
    }

    /**
     * Whether publishing has something to put on the site: the author isn't
     * there, or it is with another version.
     */
    public function hasSomethingToPublish(): bool
    {
        return AuthorStatus::Published !== $this->status || $this->hasUnpublishedChanges();
    }

    public function canBeUnpublished(): bool
    {
        return AuthorStatus::Published === $this->status;
    }

    public function canBeDeleted(): bool
    {
        return AuthorStatus::Published !== $this->status;
    }

    /**
     * Saves the author, then tells the rest of the system what happened to it
     * since it was loaded, and removes the avatars neither version has any
     * longer. When saving fails, the avatars kept since it was loaded are
     * removed instead, and the ones left stay: the author saved still has
     * them.
     */
    public function save(AuthorRepository $authorRepository, EventBus $eventBus, AvatarStorage $avatarStorage): void
    {
        $events = $this->pullDomainEvents();
        $kept = $this->keptAvatars;
        $left = $this->leftAvatars;
        $this->keptAvatars = [];
        $this->leftAvatars = [];

        try {
            $authorRepository->save($this);
        } catch (\Throwable $exception) {
            foreach ($kept as $avatar) {
                $avatarStorage->delete($avatar);
            }

            throw $exception;
        }

        foreach ($left as $avatar) {
            $avatarStorage->delete($avatar);
        }

        $eventBus->publish(...$events);
    }

    /**
     * Deletes an author off the site that signs no post, with its avatars.
     */
    public function delete(AuthorRepository $authorRepository, EventBus $eventBus, AvatarStorage $avatarStorage, AuthorUsage $authorUsage): void
    {
        if (AuthorStatus::Published === $this->status) {
            throw InvalidAuthorPublication::publishedAuthor();
        }

        if ($authorUsage->isInUse($this->id)) {
            throw InvalidAuthorPublication::usedAuthor();
        }

        // An author deleted embeds no image, nor links to any page, any longer.
        $used = $this->resourcesUsed();

        if ([] !== $used['images']) {
            $this->record(new AuthorImagesUsed($this->id->value(), []));
        }

        if ([] !== $used['pages']) {
            $this->record(new AuthorPagesLinked($this->id->value(), []));
        }

        $events = $this->pullDomainEvents();
        $authorRepository->delete($this);

        foreach ($this->avatars() as $avatar) {
            $avatarStorage->delete($avatar);
        }

        $eventBus->publish(...$events);
    }

    public function id(): AuthorId
    {
        return $this->id;
    }

    public function name(): AuthorName
    {
        return $this->version->name();
    }

    /**
     * Null while the author is left without one.
     */
    public function slug(): ?Slug
    {
        return $this->version->slug();
    }

    /**
     * The avatar of the version being edited, null until one is uploaded.
     */
    public function avatar(): ?AvatarPath
    {
        return $this->version->avatar();
    }

    public function bio(): RichText
    {
        return $this->version->bio();
    }

    public function seo(): Seo
    {
        return $this->version->seo();
    }

    public function status(): AuthorStatus
    {
        return $this->status;
    }

    /**
     * The slug the author has on the site, null while it isn't there.
     */
    public function publishedSlug(): ?Slug
    {
        return $this->publishedSlug;
    }

    /**
     * The slug it has on the site, or last had there once off it: null for an
     * author never published, and for one whose last address another took.
     */
    public function lastSiteSlug(): ?Slug
    {
        return $this->lastSiteSlug;
    }

    /**
     * Whether the file is the avatar of either version, the one being edited
     * or the one last published.
     */
    public function hasAvatar(AvatarPath $avatar): bool
    {
        foreach ($this->avatars() as $kept) {
            if ($kept->equals($avatar)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The version the site serves, null while the author isn't on it.
     */
    public function onSite(): ?AuthorVersion
    {
        return AuthorStatus::Published === $this->status ? $this->published : null;
    }

    public function timeline(): Timeline
    {
        return $this->timeline;
    }

    /**
     * Makes a change and, when it changes the images the bio embeds or the
     * pages it links to, tells which it uses now; the avatars neither version
     * has any longer are left to remove.
     *
     * @param \Closure(): void $change
     */
    private function change(\Closure $change): void
    {
        $before = $this->resourcesUsed();
        $avatars = $this->avatars();
        $change();
        $after = $this->resourcesUsed();

        if ($before['images'] !== $after['images']) {
            $this->record(new AuthorImagesUsed($this->id->value(), $after['images']));
        }

        if ($before['pages'] !== $after['pages']) {
            $this->record(new AuthorPagesLinked($this->id->value(), $after['pages']));
        }

        foreach ($avatars as $avatar) {
            if (!$this->hasAvatar($avatar)) {
                $this->leftAvatars[] = $avatar;
            }
        }
    }

    /**
     * The images embedded in, and the pages linked from, the bio being
     * edited and the one last published, each once and in order.
     *
     * @return array{images: list<string>, pages: list<string>}
     */
    private function resourcesUsed(): array
    {
        $images = [...$this->version->bio()->imageIds(), ...($this->published?->bio()->imageIds() ?? [])];
        $pages = [...$this->version->bio()->pageIds(), ...($this->published?->bio()->pageIds() ?? [])];
        sort($images);
        sort($pages);

        return ['images' => array_values(array_unique($images)), 'pages' => array_values(array_unique($pages))];
    }

    /**
     * The avatars of the version being edited and of the one last published,
     * each once.
     *
     * @return list<AvatarPath>
     */
    private function avatars(): array
    {
        $avatars = [];

        foreach ([$this->version->avatar(), $this->published?->avatar()] as $avatar) {
            if (null !== $avatar) {
                $avatars[$avatar->value()] = $avatar;
            }
        }

        return array_values($avatars);
    }

    private function replaceVersion(AuthorVersion $version): void
    {
        if (!$version->equals($this->version)) {
            $this->timeline = $this->timeline->updated(Clock::now());
        }

        $this->version = $version;
    }
}
