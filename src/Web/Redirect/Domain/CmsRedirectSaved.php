<?php

declare(strict_types=1);

namespace Web\Redirect\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedDomainEvent;

/**
 * The CMS created or changed a redirect: its address, where it leads, if
 * anywhere, and the resource that was there, if known.
 *
 * It carries what the site uses, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class CmsRedirectSaved extends RevisedDomainEvent
{
    /**
     * @param string  $source     the address, as SitePath writes it; the address of its file, for an image
     * @param ?string $targetType "page" or "image", null when the address is gone
     * @param ?string $originType "page" or "image", null when no resource is known to have been there
     */
    public function __construct(
        string $aggregateId,
        private string $source,
        private ?string $targetType,
        private ?string $targetId,
        private ?string $originType,
        private ?string $originId,
        string $eventId,
        \DateTimeImmutable $occurredOn,
        int $revision,
    ) {
        parent::__construct($aggregateId, $eventId, $occurredOn, $revision);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.redirect.saved';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body, string $eventId, \DateTimeImmutable $occurredOn): static
    {
        $body = new EventBody($body);

        return new static(
            $aggregateId,
            $body->string('source'),
            $body->nullableString('target_type'),
            $body->nullableString('target_id'),
            $body->nullableString('origin_type'),
            $body->nullableString('origin_id'),
            $eventId,
            $occurredOn,
            $body->integer('revision'),
        );
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return [
            'source' => $this->source,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'origin_type' => $this->originType,
            'origin_id' => $this->originId,
            'revision' => $this->revision(),
        ];
    }

    public function source(): string
    {
        return $this->source;
    }

    public function targetType(): ?string
    {
        return $this->targetType;
    }

    public function targetId(): ?string
    {
        return $this->targetId;
    }

    public function originType(): ?string
    {
        return $this->originType;
    }

    public function originId(): ?string
    {
        return $this->originId;
    }
}
