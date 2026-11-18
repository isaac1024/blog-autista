<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Cms\Shared\Domain\PublicationTimeline;
use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\AggregateRoot;
use Shared\Domain\Clock;
use Shared\Domain\Event\EventBus;
use Shared\Domain\External\ImageShape;
use Shared\Domain\External\Provider;
use Shared\Domain\External\Providers;
use Shared\Domain\External\ResourceKind;

/**
 * Something of another site the blog links to or embeds, such as a video, a
 * post of a social network or a study, kept once so that texts and menus
 * use it by its id: publishing it, unpublishing it or changing its address
 * reaches every place it is used.
 *
 * Editors work on its version in the CMS; once published, the site paints a
 * copy of it, which only changes when the resource is published again.
 *
 * - What it is, and the provider that embeds it, if any, are told by its
 *   address when it is created, and never change: its address only changes
 *   to another of the same kind and provider.
 * - Editing saves whatever valid fields an editor sets, complete or not;
 *   filling it from its address reads them, and its image, anew.
 * - It is published only with everything its card or its facade shows.
 * - Checking its address tells whether it is still available, which the
 *   site learns at once: an unavailable resource is painted nowhere while
 *   it stays published. Availability is that of the address on the site;
 *   publishing a new address makes it available until checked again.
 * - A published resource is unpublished before it is deleted, and nothing
 *   may use it then.
 * - Each image read is a file of its own. The one being edited and the one
 *   on the site are kept while either version has it, and removed once
 *   neither does.
 *
 * Its rules reach every value object, error and event of the resource, so
 * its coupling, its methods and its length grow with what a resource is.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.ExcessiveClassLength")
 * @SuppressWarnings("PHPMD.TooManyMethods")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
#[ORM\Entity]
#[ORM\Table(name: 'cms_external_resource')]
#[ORM\UniqueConstraint(name: 'uniq_cms_external_resource_url_hash', columns: ['url_hash'])]
#[ORM\Index(name: 'idx_cms_external_resource_availability', columns: ['status', 'availability'])]
final class ExternalResource extends AggregateRoot
{
    /**
     * What the address being edited is known by, and its title, apart from
     * the version so they can be indexed: the address kept unique, the
     * title searched.
     */
    #[ORM\Column(name: 'url_hash', length: 64)]
    private string $urlHash;

    #[ORM\Column(length: ResourceTitle::MAX_LENGTH)]
    private string $title;

    /**
     * Files of images kept since the resource was loaded, removed again if
     * saving it fails. Not persisted.
     *
     * @var list<ResourceImagePath>
     */
    private array $keptImages = [];

    /**
     * Files of images neither version has any longer, removed once the
     * resource is saved. Not persisted.
     *
     * @var list<ResourceImagePath>
     */
    private array $leftImages = [];

    /**
     * One argument per mapped field: Doctrine hydrates it without calling
     * this, and the named constructor starts each field.
     *
     * @param ?string $provider the name of the provider that embeds it, if any
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'cms_external_resource_id')]
        private readonly ExternalResourceId $id,
        #[ORM\Column(length: 20, enumType: ResourceKind::class)]
        private readonly ResourceKind $kind,
        #[ORM\Column(length: 20, nullable: true)]
        private readonly ?string $provider,
        #[ORM\Column(name: 'version', type: 'cms_external_resource_version')]
        private ResourceVersion $version,
        #[ORM\Column(length: 20, enumType: ResourceStatus::class)]
        private ResourceStatus $status,
        /*
         * The version last published, kept when the resource is unpublished.
         */
        #[ORM\Column(name: 'published_version', type: 'cms_external_resource_version', nullable: true)]
        private ?ResourceVersion $published,
        #[ORM\Column(length: 20, enumType: Availability::class)]
        private Availability $availability,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $checkedAt,
        #[ORM\Embedded(class: PublicationTimeline::class, columnPrefix: false)]
        private PublicationTimeline $timeline,
        #[ORM\Column]
        private int $publicationRevision,
    ) {
        $this->urlHash = $version->url()->hash();
        $this->title = $version->title()->value();
    }

    /**
     * Starts a resource from what reading its address told, with the image
     * read, if any. Its kind and its provider are those of the reading from
     * then on.
     */
    public static function create(string $id, ResourceUrl $url, Reading $reading, ResourceImageFetcher $imageFetcher, ResourceImageStorage $imageStorage): self
    {
        $resource = new self(
            new ExternalResourceId($id),
            $reading->kind,
            $reading->provider,
            ResourceVersion::read($url, $reading, Language::Spanish),
            ResourceStatus::Draft,
            null,
            Availability::Available,
            null,
            PublicationTimeline::start(Clock::now()),
            0,
        );
        $resource->keepImageRead($reading, $imageFetcher, $imageStorage);

        return $resource;
    }

    /**
     * Replaces everything an editor sets but the image: the address, the
     * title, the summary, the language and the details. The address only
     * changes to another of the same kind and provider; when it changes, so
     * does how the provider names the resource.
     *
     * On a published resource, it only changes the version being edited:
     * the site keeps its version until the resource is published again.
     *
     * @param array<array-key, mixed> $details as ResourceDetails reads them
     */
    public function edit(string $url, string $title, string $summary, string $language, array $details): void
    {
        // Everything is validated before anything is assigned, so an invalid
        // field doesn't leave the resource with only part of the edit.
        $url = new ResourceUrl($url);
        $locator = $this->version->locator();

        if (!$url->equals($this->version->url())) {
            $source = ResourceSource::fromUrl($url, new Providers());

            if (!$source->fits($this->kind, $this->provider)) {
                throw InvalidResourceUrl::anotherKind();
            }

            $locator = $source->locator();
        }

        $version = new ResourceVersion(
            $url,
            $locator,
            new ResourceTitle($title),
            $summary,
            Language::tryFrom($language) ?? throw new \InvalidArgumentException(\sprintf('"%s" is not a language of the resources.', $language)),
            ResourceDetails::fromPrimitives($this->kind, $details),
            $this->version->image(),
        );

        // A resource never published keeps the availability of the address
        // being edited; once published, that of the one on the site.
        if (null === $this->published) {
            $this->forgetCheckOf($url);
        }

        $this->change(fn () => $this->replaceVersion($version));
    }

    /**
     * Fills the version being edited from what reading its address told
     * anew: its title, summary, details and image, and how its provider
     * names it. What the reading doesn't tell stays as it was.
     */
    public function fill(Reading $reading, ResourceImageFetcher $imageFetcher, ResourceImageStorage $imageStorage): void
    {
        if ($reading->kind !== $this->kind || $reading->provider !== $this->provider) {
            throw InvalidResourceUrl::anotherKind();
        }

        if (!$reading->reached) {
            return;
        }

        $read = ResourceVersion::read($this->version->url(), $reading, $this->version->language());
        $this->change(fn () => $this->replaceVersion($read->withImage($this->version->image())));
        $this->keepImageRead($reading, $imageFetcher, $imageStorage);
    }

    /**
     * Puts the version last saved on the site, for the first time or in place
     * of the one there, once it has everything the site shows. A new address
     * on the site is available until it is checked; the one last checked, even
     * as a draft, keeps what the check found.
     */
    public function publish(): void
    {
        $now = Clock::now();
        $this->version->assertPublishable($this->kind, $this->providerOf());

        if (!$this->hasSomethingToPublish()) {
            throw InvalidResourcePublication::nothingToPublish();
        }

        $this->forgetCheckOf($this->version->url());
        $this->status = ResourceStatus::Published;
        $this->change(function (): void {
            $this->published = $this->version;
        });
        $this->timeline = $this->timeline->published($now);
        ++$this->publicationRevision;

        $this->record(new ExternalResourcePublished(
            $this->id->value(),
            $this->kind->value,
            $this->provider,
            $this->version->locator(),
            $this->version->url()->value(),
            $this->version->title()->value(),
            $this->version->summary(),
            $this->version->language()->value,
            $this->version->details()->toArray(),
            $this->version->image()?->value(),
            null === $this->version->image() ? null : $this->imageShape()?->value,
            Availability::Available === $this->availability,
            $this->publicationRevision,
        ));
    }

    /**
     * Takes the resource off the site.
     */
    public function unpublish(): void
    {
        if (ResourceStatus::Published !== $this->status) {
            throw InvalidResourcePublication::notPublished();
        }

        $this->status = ResourceStatus::Unpublished;
        ++$this->publicationRevision;

        $this->record(new ExternalResourceUnpublished($this->id->value(), $this->publicationRevision));
    }

    /**
     * Brings the version being edited back to the one on the site.
     */
    public function discardChanges(): void
    {
        if (null === $this->published || !$this->hasUnpublishedChanges()) {
            throw InvalidResourcePublication::noChangesToDiscard();
        }

        $published = $this->published;
        $this->change(fn () => $this->replaceVersion($published));
    }

    /**
     * Takes what checking its address found: whether it is available, when
     * that tells it, and when it was checked. A published resource tells the
     * site at once when that changes.
     */
    public function check(CheckOutcome $outcome): void
    {
        $this->checkedAt = Clock::now();
        $availability = $outcome->availability();

        if (null === $availability || $availability === $this->availability) {
            return;
        }

        $this->availability = $availability;

        if (ResourceStatus::Published === $this->status) {
            ++$this->publicationRevision;
            $this->record(new ExternalResourceAvailabilityChanged($this->id->value(), Availability::Available === $availability, $this->publicationRevision));
        }
    }

    /**
     * The version whose address the resource keeps the availability of: that
     * on the site, or the one being edited for a resource never published.
     */
    public function checked(): ResourceVersion
    {
        return $this->published ?? $this->version;
    }

    /**
     * Whether the resource is on the site with a version other than the one
     * being edited, so its changes can be discarded.
     */
    public function hasUnpublishedChanges(): bool
    {
        return ResourceStatus::Published === $this->status
            && null !== $this->published
            && !$this->published->equals($this->version);
    }

    /**
     * Whether publishing has something to put on the site: the resource
     * isn't there, or it is with another version.
     */
    public function hasSomethingToPublish(): bool
    {
        return ResourceStatus::Published !== $this->status || $this->hasUnpublishedChanges();
    }

    public function canBeUnpublished(): bool
    {
        return ResourceStatus::Published === $this->status;
    }

    public function canBeDeleted(): bool
    {
        return ResourceStatus::Published !== $this->status;
    }

    /**
     * Saves the resource, then tells the rest of the system what happened to
     * it since it was loaded, and removes the images neither version has any
     * longer. When saving fails, the images kept since it was loaded are
     * removed instead, and the ones left stay: the resource saved still has
     * them.
     */
    public function save(ExternalResourceRepository $resourceRepository, EventBus $eventBus, ResourceImageStorage $imageStorage): void
    {
        $events = $this->pullDomainEvents();
        $kept = $this->keptImages;
        $left = $this->leftImages;
        $this->keptImages = [];
        $this->leftImages = [];

        try {
            $resourceRepository->save($this);
        } catch (\Throwable $exception) {
            foreach ($kept as $image) {
                $imageStorage->delete($image);
            }

            throw $exception;
        }

        foreach ($left as $image) {
            $imageStorage->delete($image);
        }

        $eventBus->publish(...$events);
    }

    /**
     * Deletes a resource off the site that nothing uses, with its images.
     */
    public function delete(ExternalResourceRepository $resourceRepository, EventBus $eventBus, ResourceImageStorage $imageStorage, ExternalResourceUsage $usage): void
    {
        if (ResourceStatus::Published === $this->status) {
            throw InvalidResourcePublication::publishedResource();
        }

        if ($usage->isInUse($this->id)) {
            throw InvalidResourcePublication::usedResource();
        }

        $events = $this->pullDomainEvents();
        $resourceRepository->delete($this);

        foreach ($this->images() as $image) {
            $imageStorage->delete($image);
        }

        $eventBus->publish(...$events);
    }

    public function id(): ExternalResourceId
    {
        return $this->id;
    }

    public function kind(): ResourceKind
    {
        return $this->kind;
    }

    /**
     * The name of the provider that embeds it, null when none does.
     */
    public function provider(): ?string
    {
        return $this->provider;
    }

    public function version(): ResourceVersion
    {
        return $this->version;
    }

    public function status(): ResourceStatus
    {
        return $this->status;
    }

    public function availability(): Availability
    {
        return $this->availability;
    }

    /**
     * When its address was last checked, null since it was published at a new
     * one, or for one never checked.
     */
    public function checkedAt(): ?\DateTimeImmutable
    {
        return $this->checkedAt;
    }

    public function timeline(): PublicationTimeline
    {
        return $this->timeline;
    }

    /**
     * Whether the file is the image of either version, the one being edited
     * or the one last published.
     */
    public function hasImage(ResourceImagePath $image): bool
    {
        foreach ($this->images() as $kept) {
            if ($kept->equals($image)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The shape of the image of the version being edited, null for a post of
     * a social network, whose facade is its text.
     */
    public function imageShape(): ?ImageShape
    {
        return $this->version->imageShape($this->kind, $this->providerOf());
    }

    /**
     * Keeps the image the reading tells as the image of the version being
     * edited, in place of any other, when it can be read: the file is kept
     * at once, under a name of its own, and removed again if saving the
     * resource fails.
     */
    private function keepImageRead(Reading $reading, ResourceImageFetcher $imageFetcher, ResourceImageStorage $imageStorage): void
    {
        $shape = $this->imageShape();
        $file = null;

        if (null === $shape) {
            return;
        }

        foreach ($reading->images as $url) {
            $file = $imageFetcher->fetch($url, $shape);

            if (null !== $file) {
                break;
            }
        }

        if (null === $file) {
            return;
        }

        $image = ResourceImagePath::random();
        $imageStorage->save($image, $file);

        $this->keptImages[] = $image;
        $this->change(fn () => $this->replaceVersion($this->version->withImage($image)));
    }

    /**
     * Makes a change, and leaves to remove the images neither version has
     * any longer.
     *
     * @param \Closure(): void $change
     */
    private function change(\Closure $change): void
    {
        $images = $this->images();
        $change();

        foreach ($images as $image) {
            if (!$this->hasImage($image)) {
                $this->leftImages[] = $image;
            }
        }
    }

    /**
     * The images of the version being edited and of the one last published,
     * each once.
     *
     * @return list<ResourceImagePath>
     */
    private function images(): array
    {
        $images = [];

        foreach ([$this->version->image(), $this->published?->image()] as $image) {
            if (null !== $image) {
                $images[$image->value()] = $image;
            }
        }

        return array_values($images);
    }

    private function providerOf(): ?Provider
    {
        return null === $this->provider ? null : new Providers()->named($this->provider);
    }

    /**
     * Takes an address that isn't the one checked as available until it is,
     * since what was found of the other tells nothing of it.
     */
    private function forgetCheckOf(ResourceUrl $url): void
    {
        if (!$url->equals($this->checked()->url())) {
            $this->availability = Availability::Available;
            $this->checkedAt = null;
        }
    }

    private function replaceVersion(ResourceVersion $version): void
    {
        if (!$version->equals($this->version)) {
            $this->timeline = $this->timeline->updated(Clock::now());
        }

        $this->version = $version;
        $this->urlHash = $version->url()->hash();
        $this->title = $version->title()->value();
    }
}
