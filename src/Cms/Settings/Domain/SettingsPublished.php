<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedDomainEvent;

/**
 * The settings were put on the site, or their version there replaced, with
 * all the site needs: the name of the blog, its default image and its menus.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class SettingsPublished extends RevisedDomainEvent
{
    /**
     * @param list<array{page_id: string, label: ?string}> $mainMenu
     * @param list<array{page_id: string, label: ?string}> $footerMenu
     */
    public function __construct(
        string $aggregateId,
        private string $name,
        private ?string $defaultImageId,
        private array $mainMenu,
        private array $footerMenu,
        string $eventId,
        \DateTimeImmutable $occurredOn,
        int $revision,
    ) {
        parent::__construct($aggregateId, $eventId, $occurredOn, $revision);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.settings.published';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body, string $eventId, \DateTimeImmutable $occurredOn): static
    {
        $version = SettingsVersion::fromPrimitives(
            \is_string($body['name'] ?? null) ? $body['name'] : '',
            \is_string($body['default_image_id'] ?? null) ? $body['default_image_id'] : '',
            \is_array($body['main_menu'] ?? null) ? $body['main_menu'] : [],
            \is_array($body['footer_menu'] ?? null) ? $body['footer_menu'] : [],
        );

        return new static(
            $aggregateId,
            $version->name()->value(),
            $version->defaultImage()?->value(),
            $version->mainMenu()->toArray(),
            $version->footerMenu()->toArray(),
            $eventId,
            $occurredOn,
            new EventBody($body)->integer('revision'),
        );
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return [
            'name' => $this->name,
            'default_image_id' => $this->defaultImageId,
            'main_menu' => $this->mainMenu,
            'footer_menu' => $this->footerMenu,
            'revision' => $this->revision(),
        ];
    }
}
