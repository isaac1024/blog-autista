<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

use Cms\Shared\Domain\PublicationTimeline;
use Cms\Shared\Domain\Robots;
use Cms\Shared\Domain\Seo;
use Cms\Shared\Domain\Slug;
use Cms\Shared\Domain\Slugger;
use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\AggregateRoot;
use Shared\Domain\Clock;
use Shared\Domain\Event\EventBus;

/**
 * A term of a taxonomy, such as a section or a tag, identified by its id and
 * addressed by its slug, which only has to be unique within its taxonomy. It
 * may be saved without a slug, and needs one to be published.
 *
 * Editors work on its version in the CMS; once published, the site serves a
 * copy of it, which only changes when the term is published again.
 *
 * - Editing saves whatever valid fields an editor writes, complete or not.
 * - A term is published only with everything the site shows: a slug and a
 *   meta description. Publishing is what checks it.
 * - A published term is unpublished before it is deleted, and stays on the
 *   site while redirects lead to it.
 * - Once off the site, it keeps the last address it had there, until
 *   another term of its taxonomy is published at it.
 *
 * The unique indexes on the slugs keep two terms of a taxonomy from sharing
 * an address, the one being edited or the one on the site; terms without
 * one share none.
 *
 * Its rules reach every value object, error and event of the term, so its
 * coupling grows with what a term is.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.TooManyMethods")
 */
#[ORM\Entity]
#[ORM\Table(name: 'cms_term')]
#[ORM\UniqueConstraint(name: 'uniq_cms_term_slug', columns: ['taxonomy', 'slug'])]
#[ORM\UniqueConstraint(name: 'uniq_cms_term_published_slug', columns: ['taxonomy', 'published_slug'])]
#[ORM\Index(name: 'idx_cms_term_last_site_slug', columns: ['taxonomy', 'last_site_slug'])]
final class Term extends AggregateRoot
{
    /**
     * The slug of the version on the site, apart from that version so it can
     * be indexed and kept unique. Null while the term isn't on the site.
     */
    #[ORM\Column(name: 'published_slug', type: 'cms_slug', length: Slug::MAX_LENGTH, nullable: true)]
    private ?Slug $publishedSlug;

    /**
     * The slug the term last had on the site, kept once it is off it, so its
     * last address can be told and found. Null for a term never published,
     * and once another term of its taxonomy is published at that address.
     */
    #[ORM\Column(name: 'last_site_slug', type: 'cms_slug', length: Slug::MAX_LENGTH, nullable: true)]
    private ?Slug $lastSiteSlug;

    /**
     * One argument per mapped field: Doctrine hydrates it without calling
     * this, and the named constructor starts each field.
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'cms_term_id')]
        private readonly TermId $id,
        #[ORM\Column(length: 20, enumType: Taxonomy::class)]
        private readonly Taxonomy $taxonomy,
        #[ORM\Embedded(class: TermVersion::class, columnPrefix: false)]
        private TermVersion $version,
        #[ORM\Column(length: 20, enumType: TermStatus::class)]
        private TermStatus $status,
        /*
         * The version last published, kept when the term is unpublished.
         */
        #[ORM\Column(name: 'published_version', type: 'cms_term_version', nullable: true)]
        private ?TermVersion $published,
        #[ORM\Embedded(class: PublicationTimeline::class, columnPrefix: false)]
        private PublicationTimeline $timeline,
        #[ORM\Column]
        private int $publicationRevision,
    ) {
        $this->publishedSlug = TermStatus::Published === $status ? $published?->slug() : null;
        $this->lastSiteSlug = $published?->slug();
    }

    /**
     * Initialises a new term of the taxonomy from its name, deriving its slug
     * from it. It starts listed in search results.
     */
    public static function create(string $id, Taxonomy $taxonomy, string $name): self
    {
        return new self(
            new TermId($id),
            $taxonomy,
            new TermVersion(
                new TermName($name),
                $taxonomy->allows(new Slug(new Slugger($name)())),
                Seo::initial(Robots::IndexFollow),
            ),
            TermStatus::Draft,
            null,
            PublicationTimeline::start(Clock::now()),
            0,
        );
    }

    /**
     * Replaces everything an editor writes: the name, the slug and how the
     * term presents itself to search engines. The slug is no longer derived
     * from the name, so editors can keep a term's address when they rename
     * it, and a blank one leaves the term without any.
     *
     * On a published term, it only changes the version being edited: the site
     * keeps its version until the term is published again.
     */
    public function edit(string $name, string $slug, string $metaDescription, string $robots): void
    {
        // Everything is validated before anything is assigned, so an invalid
        // field doesn't leave the term with only part of the edit.
        $this->replaceVersion(new TermVersion(
            new TermName($name),
            $this->taxonomy->slugFrom($slug),
            Seo::fromPrimitives($metaDescription, $robots),
        ));
    }

    /**
     * Puts the version last saved on the site, for the first time or in place
     * of the one there, once it has everything the site shows. A section
     * never takes the slug posts of another section still carry in their
     * addresses, which would then lead to it.
     */
    public function publish(PostsUnderSectionSlugs $postsUnderSectionSlugs): void
    {
        $now = Clock::now();
        $this->version->assertPublishable();
        $slug = $this->version->slug();

        if (Taxonomy::Section === $this->taxonomy && null !== $slug) {
            $others = $postsUnderSectionSlugs->countedBySection($slug);
            unset($others[$this->id->value()]);

            if ([] !== $others) {
                throw InvalidTermPublication::slugOfPostsOfAnotherSection($slug);
            }
        }

        if (!$this->hasSomethingToPublish()) {
            throw InvalidTermPublication::nothingToPublish();
        }

        $this->status = TermStatus::Published;
        $this->published = $this->version;
        $this->publishedSlug = $this->version->slug();
        $this->lastSiteSlug = $this->version->slug();
        $this->timeline = $this->timeline->published($now);
        ++$this->publicationRevision;

        $this->record(new TermPublished(
            $this->id->value(),
            $this->taxonomy->value,
            $this->version->name()->value(),
            (string) $this->version->slug()?->value(),
            (string) $this->version->seo()->description(),
            $this->version->seo()->robots()->value,
            ($this->timeline->firstPublishedAt() ?? $now)->format(\DATE_ATOM),
            $now->format(\DATE_ATOM),
            $this->publicationRevision,
        ));
    }

    /**
     * Takes the term off the site, freeing its address there, unless
     * redirects lead to it: it stays on the site until they lead elsewhere.
     */
    public function unpublish(TermRedirects $termRedirects): void
    {
        if (TermStatus::Published !== $this->status) {
            throw InvalidTermPublication::notPublished();
        }

        if ($termRedirects->isTargeted($this->id)) {
            throw InvalidTermPublication::redirected();
        }

        $this->status = TermStatus::Unpublished;
        $this->publishedSlug = null;
        ++$this->publicationRevision;

        $this->record(new TermUnpublished($this->id->value(), $this->publicationRevision));
    }

    /**
     * Forgets the address it last had on the site, once another term of its
     * taxonomy is published there: it was the last of that other term from
     * then on.
     */
    public function loseLastAddress(): void
    {
        if (TermStatus::Published === $this->status) {
            throw InvalidTermPublication::addressOnSite();
        }

        $this->lastSiteSlug = null;
    }

    /**
     * Brings the version being edited back to the one on the site.
     */
    public function discardChanges(): void
    {
        if (null === $this->published || !$this->hasUnpublishedChanges()) {
            throw InvalidTermPublication::noChangesToDiscard();
        }

        $this->replaceVersion($this->published);
    }

    /**
     * Whether the term is on the site with a version other than the one being
     * edited, so its changes can be discarded.
     */
    public function hasUnpublishedChanges(): bool
    {
        return TermStatus::Published === $this->status
            && null !== $this->published
            && !$this->published->equals($this->version);
    }

    /**
     * Whether publishing has something to put on the site: the term isn't
     * there, or it is with another version.
     */
    public function hasSomethingToPublish(): bool
    {
        return TermStatus::Published !== $this->status || $this->hasUnpublishedChanges();
    }

    public function canBeUnpublished(): bool
    {
        return TermStatus::Published === $this->status;
    }

    public function canBeDeleted(): bool
    {
        return TermStatus::Published !== $this->status;
    }

    /**
     * Saves the term, then tells the rest of the system what happened to it
     * since it was loaded. It takes its events before saving, so the saved
     * term holds none, and none is told twice.
     */
    public function save(TermRepository $termRepository, EventBus $eventBus): void
    {
        $events = $this->pullDomainEvents();
        $termRepository->save($this);
        $eventBus->publish(...$events);
    }

    /**
     * Deletes a term off the site.
     */
    public function delete(TermRepository $termRepository, TermUsage $termUsage): void
    {
        if (TermStatus::Published === $this->status) {
            throw InvalidTermPublication::publishedTerm();
        }

        if ($termUsage->isInUse($this->id)) {
            throw InvalidTermPublication::usedTerm();
        }

        $termRepository->delete($this);
    }

    public function id(): TermId
    {
        return $this->id;
    }

    public function taxonomy(): Taxonomy
    {
        return $this->taxonomy;
    }

    public function name(): TermName
    {
        return $this->version->name();
    }

    /**
     * Null while the term is left without one.
     */
    public function slug(): ?Slug
    {
        return $this->version->slug();
    }

    public function seo(): Seo
    {
        return $this->version->seo();
    }

    public function status(): TermStatus
    {
        return $this->status;
    }

    /**
     * How many posts of the section on the site its publication leaves at
     * the address of the slug the section has there now, which it changes:
     * each moves to the new one once published again. None for a tag, or a
     * section whose slug doesn't change.
     */
    public function postsLeftBehind(PostsUnderSectionSlugs $postsUnderSectionSlugs): int
    {
        $slug = $this->version->slug();

        if (Taxonomy::Section !== $this->taxonomy || null === $this->publishedSlug || null === $slug || $slug->value() === $this->publishedSlug->value()) {
            return 0;
        }

        return $postsUnderSectionSlugs->countedBySection($this->publishedSlug)[$this->id->value()] ?? 0;
    }

    /**
     * The slug the term has on the site, null while it isn't there.
     */
    public function publishedSlug(): ?Slug
    {
        return $this->publishedSlug;
    }

    /**
     * The slug it has on the site, or last had there once off it: null for a
     * term never published, and for one whose last address another took.
     */
    public function lastSiteSlug(): ?Slug
    {
        return $this->lastSiteSlug;
    }

    /**
     * The version the site serves, null while the term isn't on it.
     */
    public function onSite(): ?TermVersion
    {
        return TermStatus::Published === $this->status ? $this->published : null;
    }

    public function timeline(): PublicationTimeline
    {
        return $this->timeline;
    }

    private function replaceVersion(TermVersion $version): void
    {
        if (!$version->equals($this->version)) {
            $this->timeline = $this->timeline->updated(Clock::now());
        }

        $this->version = $version;
    }
}
