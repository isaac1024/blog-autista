<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

use Shared\Domain\Event\DeliveredImmediately;
use Shared\Domain\Event\DomainEvent;

/**
 * The posts, sections and tags, authors and external resources the menus of
 * the settings lead to changed: those of the version being edited and of
 * the one last published, together. It is told at once, so whoever keeps
 * track of what is linked is never behind the settings.
 */
final readonly class SettingsContentsLinked extends DomainEvent implements DeliveredImmediately
{
    /**
     * @param list<string> $postIds
     * @param list<string> $termIds
     * @param list<string> $authorIds
     * @param list<string> $externalIds
     */
    public function __construct(
        string $aggregateId,
        private array $postIds,
        private array $termIds,
        private array $authorIds,
        private array $externalIds,
    ) {
        parent::__construct($aggregateId);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.settings.contents_linked';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['post_ids' => $this->postIds, 'term_ids' => $this->termIds, 'author_ids' => $this->authorIds, 'external_ids' => $this->externalIds];
    }
}
