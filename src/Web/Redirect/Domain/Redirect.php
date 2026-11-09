<?php

declare(strict_types=1);

namespace Web\Redirect\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * A redirect on the site: an address it answers, leading to a resource on
 * it or saying what was there is gone, as the CMS last saved it.
 *
 * Events can come late or twice: the projection that hands them over only
 * lets through those newer than the last it applied, by their revision. That
 * orders the events of one redirect, not those of two, so the CMS may delete
 * one and create another at the same address and the site take them the
 * other way round. So an address is not unique here: while two redirects
 * answer it, the one last changed does.
 */
#[ORM\Entity]
#[ORM\Table(name: 'web_redirect')]
#[ORM\Index(name: 'idx_web_redirect_source', columns: ['source'])]
#[ORM\Index(name: 'idx_web_redirect_origin', columns: ['origin_type', 'origin_id'])]
final class Redirect
{
    /**
     * One argument per mapped field.
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'web_redirect_id')]
        private readonly RedirectId $id,
        #[ORM\Column(length: 255)]
        private readonly string $source,
        #[ORM\Column(length: 20, nullable: true)]
        private ?string $targetType,
        #[ORM\Column(length: 36, nullable: true)]
        private ?string $targetId,
        #[ORM\Column(length: 20, nullable: true)]
        private ?string $originType,
        #[ORM\Column(length: 36, nullable: true)]
        private ?string $originId,
        /*
         * When the CMS last changed it, which tells the newest of two at the
         * same address.
         */
        #[ORM\Column]
        private \DateTimeImmutable $changedAt,
    ) {}

    public static function create(CmsRedirectSaved $event): self
    {
        return new self(
            new RedirectId($event->aggregateId()),
            $event->source(),
            $event->targetType(),
            $event->targetId(),
            $event->originType(),
            $event->originId(),
            $event->occurredOn(),
        );
    }

    /**
     * Takes where the CMS leads it now. Its address never changes.
     */
    public function change(CmsRedirectSaved $event): void
    {
        $this->targetType = $event->targetType();
        $this->targetId = $event->targetId();
        $this->originType = $event->originType();
        $this->originId = $event->originId();
        $this->changedAt = $event->occurredOn();
    }

    public function save(RedirectRepository $redirectRepository): void
    {
        $redirectRepository->save($this);
    }

    public function delete(RedirectRepository $redirectRepository): void
    {
        $redirectRepository->delete($this);
    }

    /**
     * The kind of resource it leads to, "page" or "image", or null when it
     * says the address is gone.
     */
    public function targetType(): ?string
    {
        return $this->targetType;
    }

    public function targetId(): ?string
    {
        return $this->targetId;
    }
}
