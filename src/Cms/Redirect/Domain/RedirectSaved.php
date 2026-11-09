<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedDomainEvent;

/**
 * A redirect was created or changed, with all the site needs to answer its
 * address: where it leads, if anywhere, and the resource that was there.
 *
 * Its revision grows with each change, so the site keeps the last one.
 *
 * It carries what the site uses, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class RedirectSaved extends RevisedDomainEvent
{
    /**
     * @param ?string $targetType one of the ResourceType values, null when the address is gone
     * @param ?string $originType one of the ResourceType values, null when no resource is known to have been there
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
}
