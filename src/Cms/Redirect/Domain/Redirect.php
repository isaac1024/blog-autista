<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\AggregateRoot;
use Shared\Domain\Clock;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Event\EventId;

/**
 * An address of the site that leads to a resource on it, with a permanent
 * redirect, or says what was there is gone for good. It isn't published: the
 * site answers as it is saved.
 *
 * - Its address is one the site doesn't answer itself, such as that of a
 *   page off the site, and has one redirect at most.
 * - It leads to a resource on the site, never to one off it, so it never
 *   leads to another redirect; and a resource it leads to stays on the site
 *   while it does.
 * - The address of an image leads to another image, at the width asked for;
 *   any other address leads to anything but an image.
 * - Its address is kept as it is created: only where it leads changes.
 *
 * When the address was the last of a resource off the site, it knows that
 * resource as its origin, so what links to it follows the redirect too.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cms_redirect')]
#[ORM\UniqueConstraint(name: 'uniq_cms_redirect_source', columns: ['source'])]
#[ORM\Index(name: 'idx_cms_redirect_target', columns: ['target_type', 'target_id'])]
#[ORM\Index(name: 'idx_cms_redirect_origin', columns: ['origin_type', 'origin_id'])]
final class Redirect extends AggregateRoot
{
    /**
     * One argument per mapped field: Doctrine hydrates it without calling
     * this, and the named constructor starts each field.
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'cms_redirect_id')]
        private readonly RedirectId $id,
        #[ORM\Embedded(class: Source::class, columnPrefix: false)]
        private readonly Source $source,
        #[ORM\Embedded(class: Target::class, columnPrefix: false)]
        private Target $target,
        #[ORM\Embedded(class: Origin::class, columnPrefix: false)]
        private Origin $origin,
        #[ORM\Embedded(class: Timeline::class, columnPrefix: false)]
        private Timeline $timeline,
        #[ORM\Column]
        private int $revision,
    ) {}

    /**
     * Starts a redirect at an address the site doesn't answer, to a target
     * it can lead to, knowing the resource last there, if any, as its origin.
     */
    public static function create(string $id, Source $source, Target $target, SiteResources $siteResources): self
    {
        if ($siteResources->isLive($source)) {
            throw InvalidRedirect::liveSource($source);
        }

        self::assertLeadsTo($source, $target, $siteResources);
        $last = $siteResources->lastAt($source);

        $redirect = new self(
            new RedirectId($id),
            $source,
            $target,
            null === $last ? Origin::none() : Origin::from($last),
            Timeline::start(Clock::now()),
            0,
        );
        $redirect->changed();

        return $redirect;
    }

    /**
     * Leads it somewhere else, or says the address is gone.
     */
    public function retarget(Target $target, SiteResources $siteResources): void
    {
        if ($target->equals($this->target)) {
            return;
        }

        self::assertLeadsTo($this->source, $target, $siteResources);
        $this->target = $target;
        $this->timeline = $this->timeline->updated(Clock::now());
        $this->changed();
    }

    /**
     * Saves the redirect, then tells the rest of the system what happened to
     * it since it was loaded. It takes its events before saving, so the saved
     * redirect holds none, and none is told twice.
     */
    public function save(RedirectRepository $redirectRepository, EventBus $eventBus): void
    {
        $events = $this->pullDomainEvents();
        $redirectRepository->save($this);
        $eventBus->publish(...$events);
    }

    /**
     * Deletes the redirect, so its address answers nothing any longer.
     */
    public function delete(RedirectRepository $redirectRepository, EventBus $eventBus): void
    {
        ++$this->revision;
        $this->record(new RedirectDeleted($this->id->value(), EventId::random()->value(), Clock::now(), $this->revision));

        $events = $this->pullDomainEvents();
        $redirectRepository->delete($this);
        $eventBus->publish(...$events);
    }

    public function id(): RedirectId
    {
        return $this->id;
    }

    public function source(): Source
    {
        return $this->source;
    }

    public function target(): Target
    {
        return $this->target;
    }

    public function origin(): Origin
    {
        return $this->origin;
    }

    public function timeline(): Timeline
    {
        return $this->timeline;
    }

    /**
     * Whether it leads to the resource, or says the address is gone, an
     * address of an image never leads but to an image, and the target, if
     * any, is on the site.
     */
    private static function assertLeadsTo(Source $source, Target $target, SiteResources $siteResources): void
    {
        $resource = $target->resource();

        if (null === $resource) {
            return;
        }

        if ($source->isImage() && !$resource->type()->isImage()) {
            throw InvalidRedirect::imageToOther();
        }

        if (!$source->isImage() && $resource->type()->isImage()) {
            throw InvalidRedirect::otherToImage();
        }

        if (!$siteResources->isOnSite($resource)) {
            throw InvalidRedirect::targetOffSite();
        }
    }

    /**
     * Tells the site where the address leads now.
     */
    private function changed(): void
    {
        ++$this->revision;
        $target = $this->target->resource();
        $origin = $this->origin->resource();

        $this->record(new RedirectSaved(
            $this->id->value(),
            $this->source->value(),
            $target?->type()->value,
            $target?->id(),
            $origin?->type()->value,
            $origin?->id(),
            EventId::random()->value(),
            Clock::now(),
            $this->revision,
        ));
    }
}
