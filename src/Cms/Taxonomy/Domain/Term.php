<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

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
 * - A published term is unpublished before it is deleted.
 *
 * The unique indexes on the slugs keep two terms of a taxonomy from sharing
 * an address, the one being edited or the one on the site; terms without
 * one share none.
 *
 * @SuppressWarnings("PHPMD.TooManyMethods")
 */
#[ORM\Entity]
#[ORM\Table(name: 'cms_term')]
#[ORM\UniqueConstraint(name: 'uniq_cms_term_slug', columns: ['taxonomy', 'slug'])]
#[ORM\UniqueConstraint(name: 'uniq_cms_term_published_slug', columns: ['taxonomy', 'published_slug'])]
final class Term extends AggregateRoot
{
    /**
     * The slug of the version on the site, apart from that version so it can
     * be indexed and kept unique. Null while the term isn't on the site.
     */
    #[ORM\Column(name: 'published_slug', type: 'cms_slug', length: Slug::MAX_LENGTH, nullable: true)]
    private ?Slug $publishedSlug;

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
        #[ORM\Embedded(class: Timeline::class, columnPrefix: false)]
        private Timeline $timeline,
        #[ORM\Column]
        private int $publicationRevision,
    ) {
        $this->publishedSlug = TermStatus::Published === $status ? $published?->slug() : null;
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
            Timeline::start(Clock::now()),
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
     * of the one there, once it has everything the site shows.
     */
    public function publish(): void
    {
        $now = Clock::now();
        $this->version->assertPublishable();

        if (!$this->hasSomethingToPublish()) {
            throw InvalidTermPublication::nothingToPublish();
        }

        $this->status = TermStatus::Published;
        $this->published = $this->version;
        $this->publishedSlug = $this->version->slug();
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
     * Takes the term off the site, freeing its address there.
     */
    public function unpublish(): void
    {
        if (TermStatus::Published !== $this->status) {
            throw InvalidTermPublication::notPublished();
        }

        $this->status = TermStatus::Unpublished;
        $this->publishedSlug = null;
        ++$this->publicationRevision;

        $this->record(new TermUnpublished($this->id->value(), $this->publicationRevision));
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
    public function delete(TermRepository $termRepository): void
    {
        if (TermStatus::Published === $this->status) {
            throw InvalidTermPublication::publishedTerm();
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
     * The slug the term has on the site, null while it isn't there.
     */
    public function publishedSlug(): ?Slug
    {
        return $this->publishedSlug;
    }

    public function timeline(): Timeline
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
